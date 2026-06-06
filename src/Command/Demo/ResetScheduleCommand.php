<?php

declare(strict_types=1);

namespace App\Command\Demo;

use App\Entity\DemandShift;
use App\Entity\Shift;
use App\Enum\ShiftStatus;
use App\Repository\DemandShiftRepository;
use App\Repository\EmployeeRepository;
use App\Repository\FacilityRepository;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:reset-schedule',
    description: 'Bereinigt und regeneriert den Demo-Schichtplan (–N bis +N Wochen ab aktueller KW)',
)]
class ResetScheduleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FacilityRepository $facilityRepository,
        private readonly DemandShiftRepository $demandShiftRepository,
        private readonly ShiftRepository $shiftRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('facility-id', null, InputOption::VALUE_REQUIRED, 'ID der Demo-Facility (Pflichtangabe – Sicherheitscheck)')
            ->addOption('weeks-before', null, InputOption::VALUE_REQUIRED, 'Wochen zurück ab Montag der aktuellen KW', 2)
            ->addOption('weeks-after', null, InputOption::VALUE_REQUIRED, 'Wochen voraus ab Montag der aktuellen KW', 2)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Zeigt geplante Änderungen, speichert nichts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('app:demo:reset-schedule');

        // Sicherheitscheck: --facility-id ist Pflicht
        $facilityId = $input->getOption('facility-id');
        if (!$facilityId) {
            $io->error([
                'Sicherheitsoption --facility-id ist erforderlich.',
                'Ohne diese Option wird der Command nicht ausgeführt um versehentliche Datenverluste zu verhindern.',
                'Beispiel: php bin/console app:demo:reset-schedule --facility-id=5',
            ]);
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $weeksBefore = max(0, (int) $input->getOption('weeks-before'));
        $weeksAfter = max(0, (int) $input->getOption('weeks-after'));

        // Schritt 1: Zeitraum berechnen (Montag der aktuellen KW ± N Wochen)
        $tz = new \DateTimeZone('Europe/Berlin');
        $now = new \DateTime('now', $tz);

        $monday = clone $now;
        $dayOfWeek = (int) $monday->format('N'); // ISO: 1=Mo, 7=So
        $monday->modify('-' . ($dayOfWeek - 1) . ' days');
        $monday->setTime(0, 0, 0);

        $startDate = (clone $monday)->modify("-{$weeksBefore} weeks");
        $endDate   = (clone $monday)->modify("+{$weeksAfter} weeks")->modify('+6 days');

        // Schritt 2: Facility laden
        $facility = $this->facilityRepository->find((int) $facilityId);
        if (!$facility) {
            $io->error("Facility mit ID {$facilityId} nicht gefunden.");
            return Command::FAILURE;
        }

        $io->section('Konfiguration');
        $io->listing([
            'Facility: ' . $facility->getTitle() . ' (ID: ' . $facility->getId() . ')',
            'Zeitraum: ' . $startDate->format('d.m.Y') . ' – ' . $endDate->format('d.m.Y'),
            'Wochen zurück: ' . $weeksBefore . ', Wochen voraus: ' . $weeksAfter,
            $dryRun ? '[DRY-RUN] Keine Änderungen werden gespeichert.' : 'Modus: LIVE',
        ]);

        // Schritt 3: Alle DemandShifts der Facility laden (werden als Vorlagen verwendet)
        /** @var DemandShift[] $demandShifts */
        $demandShifts = $this->demandShiftRepository->findBy(['facility' => (int) $facilityId]);

        if (empty($demandShifts)) {
            $io->error('Keine DemandShifts für diese Facility gefunden. Bitte zuerst DemandShifts in der Admin-Oberfläche anlegen.');
            return Command::FAILURE;
        }

        // Schritt 4: Bestehende Shifts im Zeitraum zählen & neue hochrechnen
        $shiftsToDelete = $this->shiftRepository->findShiftsInDateRange($startDate, $endDate, (int) $facilityId);
        $estimatedNewShifts = $this->calculateNewShiftCount($demandShifts, $startDate, $endDate);

        $io->section('Geplante Änderungen');
        $io->table(
            ['', 'Anzahl'],
            [
                ['Shifts zum Löschen (Zeitraum)', count($shiftsToDelete)],
                ['DemandShifts als Vorlage', count($demandShifts)],
                ['Neue Shifts (geschätzt)', $estimatedNewShifts],
            ]
        );

        $io->section('DemandShift-Vorlagen');
        foreach ($demandShifts as $ds) {
            $validRange = ($ds->getValidFrom() || $ds->getValidTo())
                ? ' | Gültig: ' . ($ds->getValidFrom()?->format('d.m.Y') ?? '–') . ' – ' . ($ds->getValidTo()?->format('d.m.Y') ?? '–')
                : '';
            $io->text(sprintf(
                '  <info>%-20s</info> %s–%s | Tage: %-14s | MA: %d | Qual: %s%s',
                $ds->getName(),
                $ds->getTimeFrom()?->format('H:i') ?? '?',
                $ds->getTimeTo()?->format('H:i') ?? '?',
                $this->formatActiveDays($ds),
                $ds->getAmountEmployees() ?? 0,
                $ds->getRequiredQualification()?->getTitle() ?? 'keine',
                $validRange
            ));
        }

        if ($dryRun) {
            $io->note('[DRY-RUN] Abbruch ohne Änderungen – keine Daten wurden verändert.');
            return Command::SUCCESS;
        }

        // Schritt 5: Employee-Pool nach Qualifikations-ID aufbauen (nur aktive, ohne PartnerCompany-Filter)
        $allEmployees = $this->employeeRepository->findAll(false);
        $employeePool = [];
        foreach ($allEmployees as $emp) {
            $qual = $emp->getQualification();
            if ($qual !== null) {
                $employeePool[$qual->getId()][] = $emp;
            }
        }

        // Schritt 6: Transaction
        $this->em->beginTransaction();
        try {
            // 6a: Shifts im Zeitraum löschen
            foreach ($shiftsToDelete as $shift) {
                $this->em->remove($shift);
            }
            $this->em->flush();
            $io->text('✓ ' . count($shiftsToDelete) . ' Shifts gelöscht');

            // 6b: Neue Shifts pro Tag erzeugen
            $shiftCounters = [];
            $createdShifts = 0;
            $warnedQuals = [];

            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dayName = strtolower($currentDate->format('l'));

                foreach ($demandShifts as $demandShift) {
                    // Wochentag-Check
                    if (!$this->isDemandShiftActiveOnDay($demandShift, $dayName)) {
                        continue;
                    }

                    // validFrom/validTo-Check
                    if (!$this->isDemandShiftValidOnDate($demandShift, $currentDate)) {
                        continue;
                    }

                    $requiredQual = $demandShift->getRequiredQualification();
                    if ($requiredQual === null) {
                        continue;
                    }

                    $qualId = $requiredQual->getId();
                    $pool = $employeePool[$qualId] ?? [];

                    if (empty($pool)) {
                        if (!isset($warnedQuals[$qualId])) {
                            $warnedQuals[$qualId] = true;
                            $io->warning(sprintf(
                                'Keine aktiven Mitarbeiter für Qualifikation "%s" – DemandShift "%s" wird übersprungen.',
                                $requiredQual->getTitle(),
                                $demandShift->getName()
                            ));
                        }
                        continue;
                    }

                    if (!isset($shiftCounters[$qualId])) {
                        $shiftCounters[$qualId] = 0;
                    }

                    $amount = max(1, $demandShift->getAmountEmployees() ?? 1);
                    for ($i = 0; $i < $amount; $i++) {
                        $employee = $pool[$shiftCounters[$qualId] % count($pool)];
                        $shiftCounters[$qualId]++;

                        $shift = new Shift();
                        $shift->setDemandShift($demandShift);
                        $shift->setEmployee($employee);
                        $shift->setDate(clone $currentDate);
                        $shift->setIsOnCall((bool) $demandShift->isOnCall());
                        $shift->setStatus($this->pickStatus($currentDate, $now));

                        $this->em->persist($shift);
                        $createdShifts++;
                    }
                }

                $currentDate->modify('+1 day');
            }

            $this->em->flush();
            $io->text('✓ ' . $createdShifts . ' Shifts erzeugt');

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler – Transaktion wurde zurückgerollt: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Demo-Schichtplan erfolgreich zurückgesetzt.');
        return Command::SUCCESS;
    }

    private function isDemandShiftActiveOnDay(DemandShift $ds, string $dayName): bool
    {
        return match ($dayName) {
            'monday'    => (bool) $ds->isOnMonday(),
            'tuesday'   => (bool) $ds->isOnTuesday(),
            'wednesday' => (bool) $ds->isOnWednesday(),
            'thursday'  => (bool) $ds->isOnThursday(),
            'friday'    => (bool) $ds->isOnFriday(),
            'saturday'  => (bool) $ds->isOnSaturday(),
            'sunday'    => (bool) $ds->isOnSunday(),
            default     => false,
        };
    }

    private function isDemandShiftValidOnDate(DemandShift $ds, \DateTime $date): bool
    {
        $validFrom = $ds->getValidFrom();
        $validTo   = $ds->getValidTo();

        $dateDay = (clone $date)->setTime(0, 0, 0);

        if ($validFrom !== null) {
            $fromDay = (clone $validFrom)->setTime(0, 0, 0);
            if ($dateDay < $fromDay) {
                return false;
            }
        }

        if ($validTo !== null) {
            $toDay = (clone $validTo)->setTime(23, 59, 59);
            if ($dateDay > $toDay) {
                return false;
            }
        }

        return true;
    }

    private function pickStatus(\DateTime $date, \DateTime $now): ShiftStatus
    {
        $dateDay = (clone $date)->setTime(0, 0, 0);
        $nowDay  = (clone $now)->setTime(0, 0, 0);

        if ($dateDay < $nowDay) {
            return ShiftStatus::Accepted;
        }

        // Zukünftig: deterministisch ~60% accepted, ~40% pending
        return (abs(crc32($date->format('Y-m-d'))) % 10 < 6)
            ? ShiftStatus::Accepted
            : ShiftStatus::Pending;
    }

    private function calculateNewShiftCount(array $demandShifts, \DateTime $startDate, \DateTime $endDate): int
    {
        $count = 0;
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dayName = strtolower($current->format('l'));
            foreach ($demandShifts as $ds) {
                if ($this->isDemandShiftActiveOnDay($ds, $dayName) && $this->isDemandShiftValidOnDate($ds, $current)) {
                    $count += max(1, $ds->getAmountEmployees() ?? 1);
                }
            }
            $current->modify('+1 day');
        }

        return $count;
    }

    private function formatActiveDays(DemandShift $ds): string
    {
        $days = [];
        if ($ds->isOnMonday())    $days[] = 'Mo';
        if ($ds->isOnTuesday())   $days[] = 'Di';
        if ($ds->isOnWednesday()) $days[] = 'Mi';
        if ($ds->isOnThursday())  $days[] = 'Do';
        if ($ds->isOnFriday())    $days[] = 'Fr';
        if ($ds->isOnSaturday())  $days[] = 'Sa';
        if ($ds->isOnSunday())    $days[] = 'So';

        return empty($days) ? 'keine' : implode(',', $days);
    }
}
