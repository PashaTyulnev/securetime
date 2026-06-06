<?php

declare(strict_types=1);

namespace App\Command\Demo;

use App\Entity\Absence;
use App\Entity\Admin\Tenant;
use App\Entity\DemandShift;
use App\Entity\Facility;
use App\Entity\Shift;
use App\Entity\TimeTracker;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Enum\ShiftStatus;
use App\Repository\DemandShiftRepository;
use App\Repository\EmployeeRepository;
use App\Repository\FacilityRepository;
use App\Repository\ShiftRepository;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:demo:reset-data',
    description: 'Setzt die komplette Demo-Datenbank zurück (Facilities, Mitarbeiter, Schichtplan, Zeiterfassung)',
)]
class ResetDataCommand extends Command
{
    private const DEMO_FACILITIES_COUNT = 6;
    private const DEMO_EMPLOYEES_COUNT  = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'doctrine.orm.admin_entity_manager')]
        private readonly EntityManagerInterface $adminEm,
        private readonly TenantContext $tenantContext,
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
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant-Subdomain (muss "demo" sein)')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Bestätigt echte Datenbankänderungen')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Zeigt geplante Änderungen, speichert nichts')
            ->addOption('weeks-before', null, InputOption::VALUE_REQUIRED, 'Wochen zurück', 2)
            ->addOption('weeks-after', null, InputOption::VALUE_REQUIRED, 'Wochen voraus', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('app:demo:reset-data');

        // ── Sicherheitschecks ───────────────────────────────────────────────
        $tenant = $input->getOption('tenant');
        if ($tenant !== 'demo') {
            $io->error([
                '--tenant=demo ist Pflicht.',
                'Dieser Command darf nur gegen die Demo-Datenbank laufen.',
                'Beispiel: php bin/console app:demo:reset-data --tenant=demo --confirm',
            ]);
            return Command::FAILURE;
        }

        $dryRun  = (bool) $input->getOption('dry-run');
        $confirm = (bool) $input->getOption('confirm');

        if (!$dryRun && !$confirm) {
            $io->error([
                'Entweder --confirm oder --dry-run ist Pflicht.',
                'Dry-Run:  php bin/console app:demo:reset-data --tenant=demo --dry-run',
                'Live-Run: php bin/console app:demo:reset-data --tenant=demo --confirm',
            ]);
            return Command::FAILURE;
        }

        $weeksBefore = max(0, (int) $input->getOption('weeks-before'));
        $weeksAfter  = max(0, (int) $input->getOption('weeks-after'));

        // ── Tenant-Verbindung setzen ────────────────────────────────────────
        $tenantEntity = $this->adminEm->getRepository(Tenant::class)->findOneBy(['subdomain' => 'demo']);
        if ($tenantEntity !== null) {
            $this->tenantContext->setCliTenant($tenantEntity);
            $io->text('<info>Tenant-DB:</info> ' . ($tenantEntity->getDatabaseUrl() ?? $tenantEntity->getDatabaseName()));
        } else {
            $io->warning('Kein Demo-Tenant in Admin-DB gefunden — Command läuft gegen Default-Datenbank (lokale Entwicklung).');
        }

        // ── Zeitraum berechnen ──────────────────────────────────────────────
        $tz  = new \DateTimeZone('Europe/Berlin');
        $now = new \DateTime('now', $tz);

        $monday    = clone $now;
        $dayOfWeek = (int) $monday->format('N');
        $monday->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);

        $startDate = (clone $monday)->modify("-{$weeksBefore} weeks");
        $endDate   = (clone $monday)->modify("+{$weeksAfter} weeks")->modify('+6 days');

        $io->section('Konfiguration');
        $io->listing([
            'Tenant:   demo' . ($tenantEntity ? '' : ' (lokal/default)'),
            'Zeitraum: ' . $startDate->format('d.m.Y') . ' – ' . $endDate->format('d.m.Y'),
            'Modus:    ' . ($dryRun ? '[DRY-RUN]' : 'LIVE'),
        ]);

        // ── Zähler für Summary ──────────────────────────────────────────────
        $stats = [
            'facilities_removed'    => 0,
            'facilities_kept'       => 0,
            'employees_deactivated' => 0,
            'employees_kept'        => 0,
            'demand_shifts_deleted' => 0,
            'demand_shifts_created' => 0,
            'shifts_deleted'        => 0,
            'shifts_created'        => 0,
            'time_tracker_deleted'  => 0,
            'time_tracker_created'  => 0,
            'absences_deleted'      => 0,
            'absences_created'      => 0,
        ];

        // ══════════════════════════════════════════════════════════════════
        // PHASE 1 — 6 Demo-Facilities auswählen
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 1 — Facilities auswählen');

        /** @var Facility[] $allFacilities */
        $allFacilities = $this->facilityRepository->findAll();

        // Aktive mit DemandShift-Anzahl bewerten
        $scored = [];
        foreach ($allFacilities as $fac) {
            $dsCount = count($this->demandShiftRepository->findBy(['facility' => $fac->getId()]));
            $scored[] = ['facility' => $fac, 'ds' => $dsCount, 'active' => $fac->isActive()];
        }

        // Sortierung: aktiv zuerst, dann DemandShifts DESC, dann Titel ASC
        usort($scored, static function (array $a, array $b): int {
            if ($a['active'] !== $b['active']) {
                return $b['active'] <=> $a['active'];
            }
            if ($a['ds'] !== $b['ds']) {
                return $b['ds'] <=> $a['ds'];
            }
            return strcmp($a['facility']->getTitle(), $b['facility']->getTitle());
        });

        $demoFacilities    = array_slice($scored, 0, self::DEMO_FACILITIES_COUNT);
        $removeFacilities  = array_slice($scored, self::DEMO_FACILITIES_COUNT);
        $demoFacilityIds   = array_map(static fn (array $r) => $r['facility']->getId(), $demoFacilities);

        $io->text('<info>Behalten (' . count($demoFacilities) . '):</info>');
        foreach ($demoFacilities as $r) {
            $io->text(sprintf('  ✓ [%d] %-35s DemandShifts: %d', $r['facility']->getId(), $r['facility']->getTitle(), $r['ds']));
        }

        $io->text('<comment>Entfernen (' . count($removeFacilities) . '):</comment>');
        foreach ($removeFacilities as $r) {
            $io->text(sprintf('  ✗ [%d] %-35s DemandShifts: %d  aktiv: %s',
                $r['facility']->getId(), $r['facility']->getTitle(), $r['ds'],
                $r['active'] ? 'ja' : 'nein'));
        }

        $stats['facilities_kept']    = count($demoFacilities);
        $stats['facilities_removed'] = count($removeFacilities);

        if ($dryRun) {
            $this->printSummary($io, $stats);
            $io->note('[DRY-RUN] Keine Änderungen vorgenommen.');
            return Command::SUCCESS;
        }

        // ══════════════════════════════════════════════════════════════════
        // PHASE 2 — Nicht-Demo-Facilities bereinigen
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 2 — Facilities bereinigen');

        $this->em->beginTransaction();
        try {
            foreach ($removeFacilities as $r) {
                /** @var Facility $fac */
                $fac    = $r['facility'];
                $facId  = $fac->getId();

                // 2a: TimeTracker mit direktem Facility-Bezug
                $deleted = $this->em->createQuery('DELETE FROM App\Entity\TimeTracker t WHERE t.facility = :fac')
                    ->setParameter('fac', $fac)->execute();
                $stats['time_tracker_deleted'] += $deleted;

                // 2b: Shifts via DemandShift der Facility
                $this->em->createQuery(
                    'DELETE FROM App\Entity\Shift s WHERE s.demandShift IN (
                        SELECT ds.id FROM App\Entity\DemandShift ds WHERE ds.facility = :fac
                    )'
                )->setParameter('fac', $fac)->execute();

                // 2c: Custom-Shifts mit dieser Facility
                $this->em->createQuery('DELETE FROM App\Entity\Shift s WHERE s.customFacility = :fac')
                    ->setParameter('fac', $fac)->execute();

                // 2d: DemandShifts (FK zu Qualification/Positions gelöst durch Join-Table auto-cleanup)
                $deleted = $this->em->createQuery('DELETE FROM App\Entity\DemandShift ds WHERE ds.facility = :fac')
                    ->setParameter('fac', $fac)->execute();
                $stats['demand_shifts_deleted'] += $deleted;

                // 2e: Contacts
                $this->em->createQuery('DELETE FROM App\Entity\Contact c WHERE c.facility = :fac')
                    ->setParameter('fac', $fac)->execute();

                // 2f: Facility selbst (auto-cascade: FacilityPositions + Address)
                $this->em->remove($this->em->getReference(Facility::class, $facId));
            }

            $this->em->flush();
            $this->em->clear();
            $io->text('✓ ' . count($removeFacilities) . ' Facilities + abhängige Daten entfernt');

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler in Phase 2: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ══════════════════════════════════════════════════════════════════
        // PHASE 3 — 10 Demo-Mitarbeiter auswählen
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 3 — Mitarbeiter auswählen');

        // Qualifikationen aus Demo-DemandShifts sammeln
        $requiredQualIds = [];
        foreach ($demoFacilityIds as $facId) {
            $demandShifts = $this->demandShiftRepository->findBy(['facility' => $facId]);
            foreach ($demandShifts as $ds) {
                $qual = $ds->getRequiredQualification();
                if ($qual) {
                    $requiredQualIds[$qual->getId()] = true;
                }
            }
        }

        // Alle Employees laden (inkl. inaktive, für vollständige Auswahl)
        $allEmployees = $this->employeeRepository->findAll(true);

        // Jeder Employee: Score nach Qualification-Match
        $employeeScored = [];
        foreach ($allEmployees as $emp) {
            $matchScore = 0;
            $qual = $emp->getQualification();
            if ($qual && isset($requiredQualIds[$qual->getId()])) {
                $matchScore = 1;
            }
            $userActive = $emp->getUser()?->isActive() ? 1 : 0;
            $isProtected = $this->isProtectedUser($emp->getUser()?->getUsername());

            $employeeScored[] = [
                'employee'    => $emp,
                'score'       => $matchScore,
                'userActive'  => $userActive,
                'protected'   => $isProtected,
                'name'        => $emp->getLastName() . ' ' . $emp->getFirstName(),
            ];
        }

        // Sortierung: protected → active → score DESC → name ASC
        usort($employeeScored, static function (array $a, array $b): int {
            if ($a['protected'] !== $b['protected']) return $b['protected'] <=> $a['protected'];
            if ($a['userActive'] !== $b['userActive']) return $b['userActive'] <=> $a['userActive'];
            if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
            return strcmp($a['name'], $b['name']);
        });

        $demoEmployees   = array_slice($employeeScored, 0, self::DEMO_EMPLOYEES_COUNT);
        $removeEmployees = array_slice($employeeScored, self::DEMO_EMPLOYEES_COUNT);
        $demoEmployeeIds = array_map(static fn (array $r) => $r['employee']->getId(), $demoEmployees);

        $io->text('<info>Behalten (' . count($demoEmployees) . '):</info>');
        foreach ($demoEmployees as $r) {
            $io->text(sprintf('  ✓ [%d] %-25s Qual: %-15s aktiv: %s%s',
                $r['employee']->getId(),
                $r['employee']->getFullName(),
                $r['employee']->getQualification()?->getTitle() ?? 'keine',
                $r['userActive'] ? 'ja' : 'nein',
                $r['protected'] ? ' [geschützt]' : ''
            ));
        }

        $stats['employees_kept']        = count($demoEmployees);
        $stats['employees_deactivated'] = count($removeEmployees);

        // Mitarbeiter außerhalb Top-10 deaktivieren (soft-delete via user.active=false)
        $this->em->beginTransaction();
        try {
            foreach ($removeEmployees as $r) {
                $user = $r['employee']->getUser();
                if ($user && $user->isActive()) {
                    $user->setActive(false);
                    $this->em->persist($user);
                }
            }
            $this->em->flush();
            $this->em->commit();
            $io->text('✓ ' . count($removeEmployees) . ' Mitarbeiter deaktiviert (user.active=false)');
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler in Phase 3: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ══════════════════════════════════════════════════════════════════
        // PHASE 4 — DemandShifts zurücksetzen
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 4 — DemandShifts zurücksetzen');

        // Standard-DemandShift-Vorlagen (Fallback wenn keine vorhanden)
        $defaultTemplates = [
            ['name' => 'Frühdienst',        'from' => '06:00', 'to' => '14:00', 'days' => [1,2,3,4,5,0,0], 'amount' => 1, 'onCall' => false],
            ['name' => 'Spätdienst',        'from' => '14:00', 'to' => '22:00', 'days' => [1,1,1,1,1,0,0], 'amount' => 1, 'onCall' => false],
            ['name' => 'Wochenenddienst',   'from' => '08:00', 'to' => '16:00', 'days' => [0,0,0,0,0,1,1], 'amount' => 1, 'onCall' => false],
            ['name' => 'Nachtbereitschaft', 'from' => '22:00', 'to' => '06:00', 'days' => [1,1,1,1,1,1,1], 'amount' => 1, 'onCall' => true],
        ];

        $firstQualification = $this->em->createQuery(
            'SELECT q FROM App\Entity\Qualification q ORDER BY q.rank DESC'
        )->setMaxResults(1)->getOneOrNullResult();

        $this->em->beginTransaction();
        try {
            $newDemandShiftsByFacility = [];

            foreach ($demoFacilityIds as $facId) {
                $facility = $this->facilityRepository->find($facId);

                // Snapshot bestehender DemandShifts (als Wert-Arrays, nicht Entity-Referenzen)
                $existingDs = $this->demandShiftRepository->findBy(['facility' => $facId]);
                $snapshots  = array_map(fn (DemandShift $ds) => $this->snapshotDemandShift($ds), $existingDs);

                // Alle Shifts der Facility löschen (gesamte History — Demo-DB)
                $deleted = $this->em->createQuery(
                    'DELETE FROM App\Entity\Shift s WHERE s.demandShift IN (
                        SELECT ds.id FROM App\Entity\DemandShift ds WHERE ds.facility = :fac
                    )'
                )->setParameter('fac', $facility)->execute();
                $stats['shifts_deleted'] += $deleted;

                $deleted = $this->em->createQuery('DELETE FROM App\Entity\Shift s WHERE s.customFacility = :fac')
                    ->setParameter('fac', $facility)->execute();
                $stats['shifts_deleted'] += $deleted;

                // Alle DemandShifts der Facility löschen
                $deleted = $this->em->createQuery('DELETE FROM App\Entity\DemandShift ds WHERE ds.facility = :fac')
                    ->setParameter('fac', $facility)->execute();
                $stats['demand_shifts_deleted'] += $deleted;

                // DemandShifts neu anlegen
                $templatesToUse = !empty($snapshots) ? $snapshots : $this->buildDefaultSnapshots($defaultTemplates, $firstQualification);

                $newDemandShiftsByFacility[$facId] = [];
                foreach ($templatesToUse as $snap) {
                    $newDs = $this->createDemandShiftFromSnapshot($snap, $facility, $startDate, $endDate);
                    $this->em->persist($newDs);
                    $newDemandShiftsByFacility[$facId][] = $newDs;
                    $stats['demand_shifts_created']++;
                }

                $this->em->flush();
                $io->text(sprintf('  ✓ [%d] %-35s → %d DemandShifts neu',
                    $facId, $facility->getTitle(), count($newDemandShiftsByFacility[$facId])));
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler in Phase 4: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ══════════════════════════════════════════════════════════════════
        // PHASE 5 — Shifts erzeugen
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 5 — Shifts erzeugen');

        // Employee-Pool nach Qualification aufbauen
        $demoEmployeeEntities = array_map(static fn (array $r) => $r['employee'], $demoEmployees);
        $employeePool         = [];
        foreach ($demoEmployeeEntities as $emp) {
            $qual = $emp->getQualification();
            if ($qual !== null) {
                $employeePool[$qual->getId()][] = $emp;
            }
        }

        $this->em->beginTransaction();
        try {
            $shiftCounters = [];
            $warnedQuals   = [];
            $createdShifts = 0;

            foreach ($demoFacilityIds as $facId) {
                $demandShifts = $newDemandShiftsByFacility[$facId] ?? [];

                $currentDate = clone $startDate;
                while ($currentDate <= $endDate) {
                    $dayName = strtolower($currentDate->format('l'));

                    foreach ($demandShifts as $demandShift) {
                        if (!$this->isDemandShiftActiveOnDay($demandShift, $dayName)) {
                            continue;
                        }
                        if (!$this->isDemandShiftValidOnDate($demandShift, $currentDate)) {
                            continue;
                        }

                        $requiredQual = $demandShift->getRequiredQualification();
                        if ($requiredQual === null) {
                            continue;
                        }

                        $qualId = $requiredQual->getId();
                        $pool   = $employeePool[$qualId] ?? [];

                        if (empty($pool)) {
                            if (!isset($warnedQuals[$qualId])) {
                                $warnedQuals[$qualId] = true;
                                $io->warning(sprintf(
                                    'Keine Demo-Mitarbeiter für Qualifikation "%s"',
                                    $requiredQual->getTitle()
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
                            $shift->setStatus($this->pickShiftStatus($currentDate, $now));

                            $this->em->persist($shift);
                            $createdShifts++;
                        }
                    }

                    $currentDate->modify('+1 day');
                }
            }

            $this->em->flush();
            $stats['shifts_created'] = $createdShifts;
            $io->text('✓ ' . $createdShifts . ' Shifts erzeugt');

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler in Phase 5: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ══════════════════════════════════════════════════════════════════
        // PHASE 6 — TimeTracker für vergangene Schichten
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 6 — Zeiterfassung erzeugen');

        // Alle User der Demo-Employees ermitteln
        $demoUsers = [];
        foreach ($demoEmployeeEntities as $emp) {
            $user = $emp->getUser();
            if ($user !== null) {
                $demoUsers[] = $user;
            }
        }

        // Bestehende TimeTracker-Einträge für Demo-User löschen
        if (!empty($demoUsers)) {
            $deleted = $this->em->createQuery(
                'DELETE FROM App\Entity\TimeTracker t WHERE t.user IN (:users)'
            )->setParameter('users', $demoUsers)->execute();
            $stats['time_tracker_deleted'] += $deleted;
        }

        // Vergangene Shifts für Demo-Facilities laden
        $nowDay    = (clone $now)->setTime(0, 0, 0);
        $pastShifts = $this->shiftRepository->findShiftsInDateRange($startDate, $nowDay, null);

        // Nur Demo-Facility-Shifts mit zugewiesenem User
        $pastShifts = array_filter($pastShifts, function (Shift $s) use ($demoFacilityIds, $demoUsers): bool {
            if ($s->getEmployee() === null) {
                return false;
            }
            $user = $s->getEmployee()->getUser();
            if ($user === null) {
                return false;
            }
            $facilityId = $s->getDemandShift()?->getFacility()?->getId();
            return $facilityId !== null && in_array($facilityId, $demoFacilityIds, true);
        });

        $createdTt = 0;
        foreach ($pastShifts as $shift) {
            $user = $shift->getEmployee()->getUser();
            $shiftBeginn = $shift->getShiftBeginn();
            $shiftEnd    = $shift->getShiftEnd();
            if ($shiftBeginn === null || $shiftEnd === null) {
                continue;
            }

            $facility = $shift->getDemandShift()?->getFacility();
            $shiftDurationMin = (int) (($shiftEnd->getTimestamp() - $shiftBeginn->getTimestamp()) / 60);

            // Check-in (Haupteintrag)
            $checkinTs = \DateTimeImmutable::createFromMutable($shiftBeginn)
                ->modify(sprintf('%+d minutes', random_int(-5, 10)));

            $mainCheckin = new TimeTracker();
            $mainCheckin->setUser($user);
            $mainCheckin->setType('checkin');
            $mainCheckin->setShiftStart(true);
            $mainCheckin->setTimestamp($checkinTs);
            $mainCheckin->setCreatedAt($checkinTs);
            $mainCheckin->setFacility($facility);
            $mainCheckin->setIsManualEntry(true);

            $this->em->persist($mainCheckin);
            $this->em->flush(); // MUSS vor Kind-Einträgen in DB sein (Listener-Requirement)

            // Optionale Pause bei Schichten > 6h (ca. 30% Chance deterministisch)
            if ($shiftDurationMin > 360 && abs(crc32($shift->getDate()->format('Y-m-d') . $user->getId())) % 10 < 3) {
                $midpoint  = $shiftBeginn->getTimestamp() + ($shiftDurationMin / 2 * 60);
                $pauseStart = \DateTimeImmutable::createFromFormat('U', (string) ($midpoint - 1800)); // 30 min vor Mitte
                $pauseEnd   = \DateTimeImmutable::createFromFormat('U', (string) ($midpoint - 900));  // 15 min vor Mitte

                $pauseEntry = new TimeTracker();
                $pauseEntry->setUser($user);
                $pauseEntry->setType('pause');
                $pauseEntry->setShiftStart(false);
                $pauseEntry->setParent($mainCheckin);
                $pauseEntry->setTimestamp($pauseStart);
                $pauseEntry->setCreatedAt($pauseStart);
                $pauseEntry->setIsManualEntry(true);
                $this->em->persist($pauseEntry);

                $pauseEndEntry = new TimeTracker();
                $pauseEndEntry->setUser($user);
                $pauseEndEntry->setType('checkin');
                $pauseEndEntry->setShiftStart(false);
                $pauseEndEntry->setParent($mainCheckin);
                $pauseEndEntry->setTimestamp($pauseEnd);
                $pauseEndEntry->setCreatedAt($pauseEnd);
                $pauseEndEntry->setIsManualEntry(true);
                $this->em->persist($pauseEndEntry);

                $this->em->flush();
                $createdTt += 2;
            }

            // Checkout
            $checkoutTs = \DateTimeImmutable::createFromMutable($shiftEnd)
                ->modify(sprintf('%+d minutes', random_int(-5, 10)));

            $checkout = new TimeTracker();
            $checkout->setUser($user);
            $checkout->setType('checkout');
            $checkout->setShiftStart(false);
            $checkout->setParent($mainCheckin);
            $checkout->setTimestamp($checkoutTs);
            $checkout->setCreatedAt($checkoutTs);
            $checkout->setFacility($facility);
            $checkout->setIsManualEntry(true);

            $this->em->persist($checkout);
            $this->em->flush();

            $createdTt += 2; // checkin + checkout
        }

        $stats['time_tracker_created'] = $createdTt;
        $io->text('✓ ' . $createdTt . ' TimeTracker-Einträge erzeugt (für ' . count($pastShifts) . ' vergangene Schichten)');

        // ══════════════════════════════════════════════════════════════════
        // PHASE 7 — Demo-Absences
        // ══════════════════════════════════════════════════════════════════
        $io->section('Phase 7 — Abwesenheiten');

        // Bestehende Absences der Demo-Employees löschen
        if (!empty($demoEmployeeEntities)) {
            $deleted = $this->em->createQuery(
                'DELETE FROM App\Entity\Absence a WHERE a.employee IN (:employees)'
            )->setParameter('employees', $demoEmployeeEntities)->execute();
            $stats['absences_deleted'] += $deleted;
        }

        // 3 realistische Absences anlegen
        $absenceDefinitions = [
            [
                'type'   => AbsenceType::Vacation,
                'status' => AbsenceStatus::Approved,
                'start'  => (new \DateTimeImmutable('now', $tz))->modify('-7 days'),
                'end'    => (new \DateTimeImmutable('now', $tz))->modify('-5 days'),
                'reason' => 'Jahresurlaub',
            ],
            [
                'type'   => AbsenceType::Sick,
                'status' => AbsenceStatus::Approved,
                'start'  => (new \DateTimeImmutable('now', $tz))->modify('+7 days'),
                'end'    => (new \DateTimeImmutable('now', $tz))->modify('+9 days'),
                'reason' => 'Krankgemeldelt',
            ],
            [
                'type'   => AbsenceType::Unavailable,
                'status' => AbsenceStatus::Pending,
                'start'  => (new \DateTimeImmutable('now', $tz))->modify('+10 days'),
                'end'    => (new \DateTimeImmutable('now', $tz))->modify('+10 days'),
                'reason' => 'Persönlicher Termin',
            ],
        ];

        $this->em->beginTransaction();
        try {
            foreach ($absenceDefinitions as $idx => $def) {
                if (!isset($demoEmployeeEntities[$idx])) {
                    break;
                }
                $absence = new Absence();
                $absence->setEmployee($demoEmployeeEntities[$idx]);
                $absence->setType($def['type']);
                $absence->setStatus($def['status']);
                $absence->setStartDate($def['start']);
                $absence->setEndDate($def['end']);
                $absence->setReason($def['reason']);
                $this->em->persist($absence);
                $stats['absences_created']++;
            }
            $this->em->flush();
            $this->em->commit();
            $io->text('✓ ' . $stats['absences_created'] . ' Abwesenheiten angelegt');
        } catch (\Throwable $e) {
            $this->em->rollback();
            $io->error('Fehler in Phase 7: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ══════════════════════════════════════════════════════════════════
        // SUMMARY
        // ══════════════════════════════════════════════════════════════════
        $this->printSummary($io, $stats);
        $io->success('Demo-Datenbank erfolgreich zurückgesetzt.');
        return Command::SUCCESS;
    }

    // ─── Hilfsmethoden ────────────────────────────────────────────────────

    private function snapshotDemandShift(DemandShift $ds): array
    {
        return [
            'name'          => $ds->getName(),
            'timeFrom'      => $ds->getTimeFrom() ? clone $ds->getTimeFrom() : null,
            'timeTo'        => $ds->getTimeTo() ? clone $ds->getTimeTo() : null,
            'amountEmployees' => $ds->getAmountEmployees() ?? 1,
            'onMonday'      => (bool) $ds->isOnMonday(),
            'onTuesday'     => (bool) $ds->isOnTuesday(),
            'onWednesday'   => (bool) $ds->isOnWednesday(),
            'onThursday'    => (bool) $ds->isOnThursday(),
            'onFriday'      => (bool) $ds->isOnFriday(),
            'onSaturday'    => (bool) $ds->isOnSaturday(),
            'onSunday'      => (bool) $ds->isOnSunday(),
            'color'         => $ds->getColor(),
            'isOnCall'      => (bool) $ds->isOnCall(),
            'qualification' => $ds->getRequiredQualification(),
            'positions'     => $ds->getRequiredPositions()->toArray(),
        ];
    }

    private function buildDefaultSnapshots(array $templates, $qualification): array
    {
        $snapshots = [];
        foreach ($templates as $tpl) {
            $timeFrom = \DateTime::createFromFormat('H:i', $tpl['from']);
            $timeTo   = \DateTime::createFromFormat('H:i', $tpl['to']);
            $snapshots[] = [
                'name'           => $tpl['name'],
                'timeFrom'       => $timeFrom ?: null,
                'timeTo'         => $timeTo ?: null,
                'amountEmployees' => $tpl['amount'],
                'onMonday'       => (bool) $tpl['days'][0],
                'onTuesday'      => (bool) $tpl['days'][1],
                'onWednesday'    => (bool) $tpl['days'][2],
                'onThursday'     => (bool) $tpl['days'][3],
                'onFriday'       => (bool) $tpl['days'][4],
                'onSaturday'     => (bool) $tpl['days'][5],
                'onSunday'       => (bool) $tpl['days'][6],
                'color'          => null,
                'isOnCall'       => $tpl['onCall'],
                'qualification'  => $qualification,
                'positions'      => [],
            ];
        }
        return $snapshots;
    }

    private function createDemandShiftFromSnapshot(array $snap, Facility $facility, \DateTime $validFrom, \DateTime $validTo): DemandShift
    {
        $ds = new DemandShift();
        $ds->setFacility($facility);
        $ds->setName($snap['name']);
        $ds->setTimeFrom($snap['timeFrom'] ? clone $snap['timeFrom'] : new \DateTime('06:00'));
        $ds->setTimeTo($snap['timeTo'] ? clone $snap['timeTo'] : new \DateTime('14:00'));
        $ds->setAmountEmployees($snap['amountEmployees']);
        $ds->setOnMonday($snap['onMonday']);
        $ds->setOnTuesday($snap['onTuesday']);
        $ds->setOnWednesday($snap['onWednesday']);
        $ds->setOnThursday($snap['onThursday']);
        $ds->setOnFriday($snap['onFriday']);
        $ds->setOnSaturday($snap['onSaturday']);
        $ds->setOnSunday($snap['onSunday']);
        $ds->setColor($snap['color']);
        $ds->setIsOnCall($snap['isOnCall']);
        $ds->setRequiredQualification($snap['qualification']);
        $ds->setValidFrom(clone $validFrom);
        $ds->setValidTo(clone $validTo);
        foreach ($snap['positions'] as $pos) {
            $ds->addRequiredPosition($pos);
        }
        return $ds;
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
        if ($validFrom !== null && $date < (clone $validFrom)->setTime(0, 0, 0)) {
            return false;
        }
        if ($validTo !== null && $date > (clone $validTo)->setTime(23, 59, 59)) {
            return false;
        }
        return true;
    }

    private function pickShiftStatus(\DateTime $date, \DateTime $now): ShiftStatus
    {
        $dateDay = (clone $date)->setTime(0, 0, 0);
        $nowDay  = (clone $now)->setTime(0, 0, 0);
        if ($dateDay < $nowDay) {
            return ShiftStatus::Accepted;
        }
        if (abs(crc32($date->format('Y-m-d'))) % 20 === 0) {
            return ShiftStatus::Declined;
        }
        return (abs(crc32($date->format('Y-m-d'))) % 10 < 6)
            ? ShiftStatus::Accepted
            : ShiftStatus::Pending;
    }

    private function isProtectedUser(?string $username): bool
    {
        if ($username === null) {
            return false;
        }
        return in_array(strtolower($username), ['admin', 'demo', 'demo-admin', 'demoadmin'], true)
            || str_contains(strtolower($username), 'demo');
    }

    private function printSummary(SymfonyStyle $io, array $stats): void
    {
        $io->section('Zusammenfassung');
        $io->table(
            ['Bereich', 'Wert'],
            [
                ['Facilities entfernt',        $stats['facilities_removed']],
                ['Facilities behalten (Demo)',  $stats['facilities_kept']],
                ['Mitarbeiter deaktiviert',     $stats['employees_deactivated']],
                ['Mitarbeiter aktiv (Demo)',    $stats['employees_kept']],
                ['DemandShifts gelöscht',       $stats['demand_shifts_deleted']],
                ['DemandShifts erstellt',       $stats['demand_shifts_created']],
                ['Shifts gelöscht',             $stats['shifts_deleted']],
                ['Shifts erstellt',             $stats['shifts_created']],
                ['TimeTracker gelöscht',        $stats['time_tracker_deleted']],
                ['TimeTracker erstellt',        $stats['time_tracker_created']],
                ['Absences gelöscht',           $stats['absences_deleted']],
                ['Absences erstellt',           $stats['absences_created']],
            ]
        );
        $io->text('<comment>Übersprungen: Contracts, PartnerCompany, Qualifications, Departments (Master-Data)</comment>');
    }
}
