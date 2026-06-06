<?php

declare(strict_types=1);

namespace App\Command\Demo;

use App\Entity\TimeTracker;
use App\Repository\ShiftRepository;
use App\Repository\TimeTrackerRepository;
use App\Service\Demo\TimeTrackerReplicator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:generate-time-tracking',
    description: 'Repliziert echte Zeiterfassung (27.04.–10.05.2026) zyklisch bis heute (Demo-DB). Voraussetzung: app:demo:generate-shifts.',
)]
class GenerateTimeTrackingCommand extends Command
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';
    private const DEFAULT_FROM = '2026-05-11';
    private const DEFAULT_UNTIL = 'today';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TimeTrackerRepository $timeTrackerRepository,
        private readonly ShiftRepository $shiftRepository,
        private readonly TimeTrackerReplicator $replicator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Startdatum der Generierung (Y-m-d)', self::DEFAULT_FROM)
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'Enddatum (Y-m-d oder "today")', self::DEFAULT_UNTIL)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Zeigt geplante Änderungen, speichert nichts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('app:demo:generate-time-tracking');

        $from = $this->parseDate((string) $input->getOption('from'));
        $until = $this->parseUntil((string) $input->getOption('until'));
        if ($from === null || $until === null) {
            $io->error('Ungültiges Datum (erwartet Y-m-d, --until akzeptiert auch "today").');

            return Command::FAILURE;
        }

        $templates = $this->timeTrackerRepository->findTemplatesInRange(
            new \DateTimeImmutable(self::TEMPLATE_START . ' 00:00:00'),
            new \DateTimeImmutable(self::TEMPLATE_END . ' 23:59:59')
        );
        if ($templates === []) {
            $io->error('Keine Template-Zeiterfassung im Zeitraum 27.04.–10.05.2026 gefunden.');

            return Command::FAILURE;
        }

        $plan = $this->replicator->buildPlan(
            $templates,
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            $from,
            $until,
            new \DateTimeImmutable('today')
        );
        $classification = $this->classify($plan);

        return (bool) $input->getOption('dry-run')
            ? $this->preview($io, count($templates), $from, $until, $classification)
            : $this->generate($io, $from, $classification);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }

    private function parseUntil(string $value): ?\DateTimeImmutable
    {
        if ($value === '' || $value === self::DEFAULT_UNTIL) {
            return new \DateTimeImmutable('today');
        }

        return $this->parseDate($value);
    }

    /**
     * Splits the plan into creatable groups and counters (future days, missing shifts, duplicates).
     *
     * @param array<int, array{entries: TimeTracker[], targetMainDate: \DateTimeImmutable, deltaDays: int, isFuture: bool}> $plan
     *
     * @return array{creatable: array<int, array{entries: TimeTracker[], deltaDays: int}>, entries: int, future: int, noShift: int, conflicts: int, reasons: string[]}
     */
    private function classify(array $plan): array
    {
        $seen = [];
        $result = ['creatable' => [], 'entries' => 0, 'future' => 0, 'noShift' => 0, 'conflicts' => 0, 'reasons' => []];
        foreach ($plan as $item) {
            $main = $this->mainEntry($item['entries']);
            $key = $this->groupKey($main, $item['targetMainDate']);

            if ($item['isFuture']) {
                ++$result['future'];
            } elseif (!$this->userHasShift($main, $item['targetMainDate'])) {
                ++$result['noShift'];
                $result['reasons'][] = sprintf('Kein Shift für %s am %s', $key, $item['targetMainDate']->format('d.m.Y'));
            } elseif (isset($seen[$key])) {
                ++$result['conflicts'];
                $result['reasons'][] = 'Doppelte Gruppe: ' . $key;
            } else {
                $seen[$key] = true;
                $result['creatable'][] = ['entries' => $item['entries'], 'deltaDays' => $item['deltaDays']];
                $result['entries'] += count($item['entries']);
            }
        }

        return $result;
    }

    /**
     * @param array{creatable: array<int, array{entries: TimeTracker[], deltaDays: int}>, entries: int, future: int, noShift: int, conflicts: int, reasons: string[]} $classification
     */
    private function preview(SymfonyStyle $io, int $templateCount, \DateTimeImmutable $from, \DateTimeImmutable $until, array $classification): int
    {
        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Template-Einträge (27.04.–10.05.2026)', (string) $templateCount],
                ['Zielzeitraum', $from->format('d.m.Y') . ' – ' . $until->format('d.m.Y')],
                ['Zu löschende Einträge (timestamp >= ' . $from->format('Y-m-d') . ')', (string) $this->timeTrackerRepository->countFromTimestamp($from)],
                ['Zu erzeugende Einträge', (string) $classification['entries']],
                ['Übersprungene zukünftige Tage', (string) $classification['future']],
                ['Übersprungen (kein Shift)', (string) $classification['noShift']],
                ['Erkannte Konflikte (Duplikate)', (string) $classification['conflicts']],
            ]
        );

        $io->note('[DRY-RUN] Keine Änderungen gespeichert.');

        return Command::SUCCESS;
    }

    /**
     * @param array{creatable: array<int, array{entries: TimeTracker[], deltaDays: int}>, entries: int, future: int, noShift: int, conflicts: int, reasons: string[]} $classification
     */
    private function generate(SymfonyStyle $io, \DateTimeImmutable $from, array $classification): int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $deleted = $this->deleteFromTimestamp($from);
            $created = 0;
            foreach ($classification['creatable'] as $group) {
                $created += $this->persistGroup($group['entries'], $group['deltaDays']);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $io->error('Fehler – Transaktion wurde zurückgerollt: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d Einträge erzeugt, %d alte gelöscht. Übersprungen: %d zukünftig, %d ohne Shift, %d Konflikte.',
            $created,
            $deleted,
            $classification['future'],
            $classification['noShift'],
            $classification['conflicts']
        ));

        return Command::SUCCESS;
    }

    /**
     * Persists a group main-first, then children (chronological) with the remapped parent id.
     *
     * @param TimeTracker[] $entries
     */
    private function persistGroup(array $entries, int $deltaDays): int
    {
        $templateMain = $this->mainEntry($entries);
        $newMain = $this->createEntry($templateMain, $deltaDays);
        $this->em->persist($newMain);
        $this->em->flush();

        $created = 1;
        foreach ($entries as $template) {
            if ($template === $templateMain) {
                continue;
            }
            $child = $this->createEntry($template, $deltaDays);
            $child->setParent($newMain);
            $this->em->persist($child);
            $this->em->flush();
            ++$created;
        }

        return $created;
    }

    private function createEntry(TimeTracker $template, int $deltaDays): TimeTracker
    {
        $entry = new TimeTracker();
        $entry->setUser($template->getUser());
        $entry->setType((string) $template->getType());
        $entry->setNote($template->getNote());
        $entry->setShiftStart((bool) $template->isShiftStart());
        $entry->setFacility($template->getFacility());
        $entry->setTimeDeviation($template->getTimeDeviation());
        $entry->setAutoCheckOut($template->isAutoCheckOut());
        $entry->setIsManualEntry($template->isManualEntry());
        $entry->setTimestamp($template->getTimestamp()->modify(sprintf('+%d days', $deltaDays)));
        $entry->setCreatedAt($template->getCreatedAt()->modify(sprintf('+%d days', $deltaDays)));

        return $entry;
    }

    /**
     * @param TimeTracker[] $entries
     */
    private function mainEntry(array $entries): TimeTracker
    {
        foreach ($entries as $entry) {
            if ($entry->getParent() === null) {
                return $entry;
            }
        }

        return $entries[0];
    }

    private function userHasShift(TimeTracker $main, \DateTimeImmutable $date): bool
    {
        $employee = $main->getUser()?->getEmployee();
        if ($employee === null) {
            return false;
        }

        return $this->shiftRepository->findByEmployeeAndDate($employee, \DateTime::createFromInterface($date)) !== [];
    }

    private function groupKey(TimeTracker $main, \DateTimeImmutable $date): string
    {
        return sprintf('%d_%s', $main->getUser()?->getId() ?? 0, $date->format('Y-m-d'));
    }

    private function deleteFromTimestamp(\DateTimeImmutable $from): int
    {
        $connection = $this->em->getConnection();
        $params = ['from' => $from->format('Y-m-d H:i:s')];

        $children = (int) $connection->executeStatement('DELETE FROM time_tracker WHERE parent_id IS NOT NULL AND timestamp >= :from', $params);
        $parents = (int) $connection->executeStatement('DELETE FROM time_tracker WHERE timestamp >= :from', $params);

        return $children + $parents;
    }
}
