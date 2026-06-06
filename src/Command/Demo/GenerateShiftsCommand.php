<?php

declare(strict_types=1);

namespace App\Command\Demo;

use App\Entity\Shift;
use App\Enum\ShiftStatus;
use App\Repository\DemandShiftRepository;
use App\Repository\ShiftRepository;
use App\Service\Demo\ShiftReplicator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:demo:generate-shifts',
    description: 'Repliziert echte Shifts (27.04.–10.05.2026) zyklisch in die Zukunft (Demo-DB). Voraussetzung: app:demo:generate-demand-shifts.',
)]
class GenerateShiftsCommand extends Command
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';
    private const DEFAULT_FROM = '2026-05-11';
    private const DEFAULT_MONTHS = '6';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShiftRepository $shiftRepository,
        private readonly DemandShiftRepository $demandShiftRepository,
        private readonly ShiftReplicator $replicator,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Startdatum der Generierung (Y-m-d)', self::DEFAULT_FROM)
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'Anzahl Monate die erzeugt werden', self::DEFAULT_MONTHS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Zeigt geplante Änderungen, speichert nichts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('app:demo:generate-shifts');

        $from = $this->parseDate((string) $input->getOption('from'));
        if ($from === null) {
            $io->error('Ungültiges --from Datum (erwartet Format Y-m-d).');

            return Command::FAILURE;
        }

        $months = max(1, (int) $input->getOption('months'));
        $until = $from->modify(sprintf('+%d months', $months));

        $templates = $this->shiftRepository->findTemplatesInRange(
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END)
        );
        if ($templates === []) {
            $io->error('Keine Template-Shifts im Zeitraum 27.04.–10.05.2026 gefunden.');

            return Command::FAILURE;
        }

        $idsToDelete = $this->shiftRepository->findIdsFromDate($from);
        $plan = $this->replicator->buildPlan(
            $templates,
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            $from,
            $until
        );

        return (bool) $input->getOption('dry-run')
            ? $this->preview($io, count($templates), $idsToDelete, $plan, $from, $until)
            : $this->generate($io, $idsToDelete, $plan);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }

    /**
     * @param int[]                                                       $idsToDelete
     * @param array<int, array{template: Shift, date: \DateTimeImmutable}> $plan
     */
    private function preview(
        SymfonyStyle $io,
        int $templateCount,
        array $idsToDelete,
        array $plan,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): int {
        $conflicts = $this->analyzeConflicts($plan);

        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Template-Shifts (27.04.–10.05.2026)', (string) $templateCount],
                ['Zielzeitraum', $from->format('d.m.Y') . ' – ' . $until->modify('-1 day')->format('d.m.Y')],
                ['Zu löschende Shifts (date >= ' . $from->format('Y-m-d') . ')', (string) count($idsToDelete)],
                ['Zu erzeugende Shifts', (string) count($plan)],
                ['Erkannte Konflikte', (string) count($conflicts)],
            ]
        );

        foreach (array_slice($conflicts, 0, 20) as $conflict) {
            $io->warning($conflict);
        }

        $io->note('[DRY-RUN] Keine Änderungen gespeichert.');

        return Command::SUCCESS;
    }

    /**
     * @param int[]                                                       $idsToDelete
     * @param array<int, array{template: Shift, date: \DateTimeImmutable}> $plan
     */
    private function generate(SymfonyStyle $io, array $idsToDelete, array $plan): int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $deleted = $this->deleteFutureShifts($idsToDelete);
            $result = $this->persistShifts($plan);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $io->error('Fehler – Transaktion wurde zurückgerollt: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $this->renderResult($io, $deleted, $result);

        return Command::SUCCESS;
    }

    /**
     * Builds, validates and persists each shift. Rule violations skip the single shift
     * (kept out of the demo data) instead of failing the whole run.
     *
     * @param array<int, array{template: Shift, date: \DateTimeImmutable}> $plan
     *
     * @return array{created: int, skipped: int, reasons: string[]}
     */
    private function persistShifts(array $plan): array
    {
        $seen = [];
        $created = 0;
        $reasons = [];
        foreach ($plan as $entry) {
            $shift = $this->buildShift($entry['template'], $entry['date']);
            $this->applyDemandShiftOrFail($entry['template'], $shift);

            $skip = $this->skipReason($seen, $shift);
            if ($skip !== null) {
                $reasons[] = $skip;
                continue;
            }

            $seen[$this->uniquenessKey($shift)] = true;
            $this->em->persist($shift);
            $this->em->flush();
            ++$created;
        }

        return ['created' => $created, 'skipped' => count($reasons), 'reasons' => $reasons];
    }

    /**
     * Returns a reason if the shift must be skipped (duplicate or rule violation), otherwise null.
     *
     * @param array<string, true> $seen
     */
    private function skipReason(array $seen, Shift $shift): ?string
    {
        if (isset($seen[$this->uniquenessKey($shift)])) {
            return 'Doppelte Schicht (Mitarbeiter/Datum): ' . $this->uniquenessKey($shift);
        }

        $violations = $this->collectViolations($shift);
        if ($violations === []) {
            return null;
        }

        return sprintf('%s übersprungen: %s', $shift->getDate()?->format('d.m.Y'), implode(' | ', $violations));
    }

    /**
     * @param array{created: int, skipped: int, reasons: string[]} $result
     */
    private function renderResult(SymfonyStyle $io, int $deleted, array $result): void
    {
        foreach (array_slice($result['reasons'], 0, 15) as $reason) {
            $io->writeln('  <comment>übersprungen:</comment> ' . $reason);
        }

        $io->success(sprintf(
            '%d Shifts erzeugt, %d übersprungen (Regelverstoß/Duplikat), %d alte Demo-Shifts gelöscht.',
            $result['created'],
            $result['skipped'],
            $deleted
        ));
    }

    /**
     * Collects all blocking issues without persisting anything (used for --dry-run).
     *
     * @param array<int, array{template: Shift, date: \DateTimeImmutable}> $plan
     *
     * @return string[]
     */
    private function analyzeConflicts(array $plan): array
    {
        $seen = [];
        $conflicts = [];
        foreach ($plan as $entry) {
            $shift = $this->buildShift($entry['template'], $entry['date']);
            $conflict = $this->detectConflict($seen, $entry['template'], $shift);
            if ($conflict !== null) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    /**
     * @param array<string, true> $seen
     */
    private function detectConflict(array &$seen, Shift $template, Shift $shift): ?string
    {
        $key = $this->uniquenessKey($shift);
        if (isset($seen[$key])) {
            return 'Doppelte Schicht (Mitarbeiter/Datum): ' . $key;
        }
        $seen[$key] = true;

        $demandError = $this->matchDemandShift($template, $shift);
        if ($demandError !== null) {
            return $demandError;
        }

        $violations = $this->collectViolations($shift);

        return $violations === [] ? null : implode(' | ', $violations);
    }

    private function buildShift(Shift $template, \DateTimeImmutable $date): Shift
    {
        $shift = new Shift();
        $shift->setEmployee($template->getEmployee());
        $shift->setNote($template->getNote());
        $shift->setStatus($template->getStatus() ?? ShiftStatus::Pending);
        $shift->setIsOnCall((bool) $template->isOnCall());
        $shift->setCustomName($template->getCustomName());
        $shift->setCustomTimeFrom($this->cloneTime($template->getCustomTimeFrom()));
        $shift->setCustomTimeTo($this->cloneTime($template->getCustomTimeTo()));
        $shift->setCustomColor($template->getCustomColor());
        $shift->setCustomFacility($template->getCustomFacility());
        $shift->setDate(\DateTime::createFromInterface($date));

        return $shift;
    }

    /**
     * Resolves the matching generated DemandShift for the target date; returns an error message if none.
     */
    private function matchDemandShift(Shift $template, Shift $shift): ?string
    {
        $templateDemand = $template->getDemandShift();
        if ($templateDemand === null) {
            return null;
        }

        $match = $this->demandShiftRepository->findMatchingForTemplate($templateDemand, $shift->getDate());
        if ($match === null) {
            return sprintf(
                'Kein passender DemandShift für "%s" am %s gefunden. Bitte zuerst app:demo:generate-demand-shifts ausführen.',
                $templateDemand->getName(),
                $shift->getDate()->format('d.m.Y')
            );
        }

        $shift->setDemandShift($match);

        return null;
    }

    private function applyDemandShiftOrFail(Shift $template, Shift $shift): void
    {
        $error = $this->matchDemandShift($template, $shift);
        if ($error !== null) {
            throw new \RuntimeException($error);
        }
    }

    /**
     * @return string[]
     */
    private function collectViolations(Shift $shift): array
    {
        $messages = [];
        foreach ($this->validator->validate($shift) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    private function uniquenessKey(Shift $shift): string
    {
        return sprintf('%d_%s', $shift->getEmployee()?->getId() ?? 0, $shift->getDate()?->format('Y-m-d'));
    }

    private function cloneTime(?\DateTime $time): ?\DateTime
    {
        return $time === null ? null : clone $time;
    }

    /**
     * @param int[] $ids
     */
    private function deleteFutureShifts(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) $this->em->getConnection()->executeStatement(
            'DELETE FROM shift WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER]
        );
    }
}
