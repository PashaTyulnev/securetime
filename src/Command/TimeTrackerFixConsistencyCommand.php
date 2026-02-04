<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TimeTracker;
use App\Repository\TimeTrackerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prüft TimeTracker-Einträge auf Inkonsistenzen und korrigiert sie:
 * - Einträge mit Timestamp vor Schichtbeginn
 * - Einträge mit Timestamp vor dem vorherigen Eintrag (z. B. Checkout vor Checkin)
 */
#[AsCommand(
    name: 'app:time-tracker-fix-consistency',
    description: 'Check and fix TimeTracker inconsistencies (monotonic timestamps per shift)',
)]
class TimeTrackerFixConsistencyCommand extends Command
{
    private const MIN_GAP_MINUTES = 1;

    public function __construct(
        private readonly TimeTrackerRepository $timeTrackerRepository,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be corrected, do not persist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run: Es werden keine Änderungen gespeichert.');
        }

        $io->title('TimeTracker Inkonsistenzen prüfen und korrigieren');

        $all = $this->timeTrackerRepository->findBy([], ['id' => 'ASC']);
        $byParent = [];
        foreach ($all as $e) {
            if ($e->isShiftStart() && $e->getParent() === null) {
                $byParent[$e->getId()][] = $e;
            } elseif ($e->getParent() !== null) {
                $byParent[$e->getParent()->getId()][] = $e;
            }
        }
        foreach ($byParent as $mainId => $entries) {
            usort($byParent[$mainId], static fn (TimeTracker $a, TimeTracker $b): int => $a->getId() <=> $b->getId());
        }

        $corrected = 0;
        $issues = [];

        foreach ($byParent as $mainId => $entries) {
            $main = $entries[0];
            if (!$main->isShiftStart() || $main->getType() !== 'checkin') {
                continue;
            }

            $mainTs = $main->getTimestamp();
            if ($mainTs === null) {
                continue;
            }

            $lastTs = $mainTs;

            for ($i = 1; $i < count($entries); $i++) {
                $e = $entries[$i];
                $ts = $e->getTimestamp();
                if ($ts === null) {
                    continue;
                }

                $needsFix = false;
                $newTs = null;

                if ($ts < $mainTs) {
                    $needsFix = true;
                    $newTs = $mainTs->add(new \DateInterval('PT1S'));
                    $issues[] = [
                        'shift' => $mainId,
                        'id' => $e->getId(),
                        'type' => 'timestamp_before_main',
                        'old' => $ts->format('Y-m-d H:i:s'),
                        'new' => $newTs->format('Y-m-d H:i:s'),
                    ];
                } elseif ($ts < $lastTs) {
                    $needsFix = true;
                    $newTs = $lastTs->add(new \DateInterval(sprintf('PT%dM', self::MIN_GAP_MINUTES)));
                    $issues[] = [
                        'shift' => $mainId,
                        'id' => $e->getId(),
                        'type' => 'timestamp_not_monotonic',
                        'old' => $ts->format('Y-m-d H:i:s'),
                        'new' => $newTs->format('Y-m-d H:i:s'),
                    ];
                }

                if ($needsFix && $newTs !== null) {
                    if (!$dryRun) {
                        $e->setTimestamp($newTs);
                        $this->em->persist($e);
                    }
                    $corrected++;
                    $lastTs = $newTs;
                } else {
                    $lastTs = $ts;
                }
            }
        }

        if (empty($issues)) {
            $io->success('Keine korrigierbaren Inkonsistenzen gefunden.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Gefunden: %d zu korrigierende Einträge', count($issues)));
        foreach ($issues as $item) {
            $io->writeln(sprintf(
                '  Shift %d, id=%d [%s]: %s → %s',
                $item['shift'],
                $item['id'],
                $item['type'],
                $item['old'],
                $item['new']
            ));
        }

        if ($dryRun) {
            $io->warning(sprintf('Dry-run: %d Korrektur(en) würden durchgeführt. Ohne --dry-run ausführen zum Speichern.', $corrected));
            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(sprintf('%d Einträge wurden korrigiert.', $corrected));

        return Command::SUCCESS;
    }
}
