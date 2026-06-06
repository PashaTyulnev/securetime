<?php

declare(strict_types=1);

namespace App\Command\Demo;

use App\Entity\DemandShift;
use App\Repository\DemandShiftRepository;
use App\Service\Demo\DemandShiftReplicator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:generate-demand-shifts',
    description: 'Repliziert das 2-Wochen-Template (27.04.–10.05.2026) zyklisch in die Zukunft (Demo-DB).',
)]
class GenerateDemandShiftsCommand extends Command
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';
    private const DEFAULT_FROM = '2026-05-11';
    private const DEFAULT_MONTHS = '6';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DemandShiftRepository $demandShiftRepository,
        private readonly DemandShiftReplicator $replicator,
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
        $io->title('app:demo:generate-demand-shifts');

        $from = $this->parseDate((string) $input->getOption('from'));
        if ($from === null) {
            $io->error('Ungültiges --from Datum (erwartet Format Y-m-d).');

            return Command::FAILURE;
        }

        $months = max(1, (int) $input->getOption('months'));
        $dryRun = (bool) $input->getOption('dry-run');

        $templateStart = new \DateTimeImmutable(self::TEMPLATE_START);
        $templateEnd = new \DateTimeImmutable(self::TEMPLATE_END);
        $until = $from->modify(sprintf('+%d months', $months));

        $templates = $this->demandShiftRepository->findTemplatesInRange($templateStart, $templateEnd);
        if ($templates === []) {
            $io->error('Keine Template-DemandShifts im Zeitraum 27.04.–10.05.2026 gefunden.');

            return Command::FAILURE;
        }

        $idsToDelete = $this->demandShiftRepository->findIdsFromDate($from);
        $newShifts = $this->replicator->replicate($templates, $templateStart, $templateEnd, $from, $until);

        $this->renderPlan($io, $templates, $idsToDelete, $newShifts, $from, $until);

        if ($dryRun) {
            $io->note('[DRY-RUN] Keine Änderungen gespeichert.');

            return Command::SUCCESS;
        }

        return $this->persist($io, $idsToDelete, $newShifts);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }

    /**
     * @param DemandShift[] $templates
     * @param int[]         $idsToDelete
     * @param DemandShift[] $newShifts
     */
    private function renderPlan(
        SymfonyStyle $io,
        array $templates,
        array $idsToDelete,
        array $newShifts,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): void {
        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Template-Datensätze (27.04.–10.05.2026)', (string) count($templates)],
                ['Zielzeitraum', $from->format('d.m.Y') . ' – ' . $until->modify('-1 day')->format('d.m.Y')],
                ['Zu löschende Demo-Datensätze (valid_from >= ' . $from->format('Y-m-d') . ')', (string) count($idsToDelete)],
                ['Neu zu erzeugende Demand-Shifts', (string) count($newShifts)],
            ]
        );
    }

    /**
     * @param int[]         $idsToDelete
     * @param DemandShift[] $newShifts
     */
    private function persist(SymfonyStyle $io, array $idsToDelete, array $newShifts): int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $deleted = $this->deleteFutureDemandShifts($idsToDelete);
            foreach ($newShifts as $shift) {
                $this->em->persist($shift);
            }
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $io->error('Fehler – Transaktion wurde zurückgerollt: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d Demand-Shifts erzeugt, %d Demo-Datensätze gelöscht (%d abhängige Shifts entfernt).',
            count($newShifts),
            $deleted['demandShifts'],
            $deleted['shifts']
        ));

        return Command::SUCCESS;
    }

    /**
     * Deletes future demo DemandShifts FK-safe (dependent shifts and join tables first).
     *
     * @param int[] $ids
     *
     * @return array{shifts: int, positions: int, partners: int, demandShifts: int}
     */
    private function deleteFutureDemandShifts(array $ids): array
    {
        if ($ids === []) {
            return ['shifts' => 0, 'positions' => 0, 'partners' => 0, 'demandShifts' => 0];
        }

        $connection = $this->em->getConnection();
        $params = ['ids' => $ids];
        $types = ['ids' => ArrayParameterType::INTEGER];

        return [
            'shifts' => (int) $connection->executeStatement('DELETE FROM shift WHERE demand_shift_id IN (:ids)', $params, $types),
            'positions' => (int) $connection->executeStatement('DELETE FROM demand_shift_facility_position WHERE demand_shift_id IN (:ids)', $params, $types),
            'partners' => (int) $connection->executeStatement('DELETE FROM demand_shift_partner_company WHERE demand_shift_id IN (:ids)', $params, $types),
            'demandShifts' => (int) $connection->executeStatement('DELETE FROM demand_shift WHERE id IN (:ids)', $params, $types),
        ];
    }
}
