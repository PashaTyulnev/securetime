<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TimeTrackerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prüft time_tracker auf Inkonsistenzen (z. B. durch fehlerhaftes Auto-Checkout oder automatische Pausen).
 */
#[AsCommand(
    name: 'app:time-tracker-consistency-check',
    description: 'Check time_tracker table for inconsistencies (auto-checkout, automatic pause)',
)]
class TimeTrackerConsistencyCheckCommand extends Command
{
    public function __construct(
        private readonly TimeTrackerRepository $timeTrackerRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TimeTracker Konsistenzprüfung');

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
            usort($byParent[$mainId], static fn ($a, $b) => $a->getId() <=> $b->getId());
        }

        $errors = [];

        foreach ($byParent as $mainId => $entries) {
            $main = $entries[0];
            if (!$main->isShiftStart() || $main->getType() !== 'checkin') {
                continue;
            }

            $mainTs = $main->getTimestamp();
            $lastTs = $mainTs;
            $lastType = 'checkin';
            $checkoutCount = 0;
            $openPause = false;

            for ($i = 1; $i < count($entries); $i++) {
                $e = $entries[$i];
                $ts = $e->getTimestamp();
                $id = $e->getId();
                $type = $e->getType();

                if ($ts < $mainTs) {
                    $errors[] = [
                        'shift' => $mainId,
                        'id' => $id,
                        'type' => 'timestamp_before_main',
                        'msg' => sprintf(
                            'Shift %d: id=%d type=%s timestamp %s is before main checkin %s',
                            $mainId,
                            $id,
                            $type,
                            $ts->format('Y-m-d H:i'),
                            $mainTs->format('Y-m-d H:i')
                        ),
                    ];
                }

                if ($ts < $lastTs) {
                    $errors[] = [
                        'shift' => $mainId,
                        'id' => $id,
                        'type' => 'timestamp_not_monotonic',
                        'msg' => sprintf(
                            'Shift %d: id=%d type=%s timestamp %s is before previous entry %s (id %s)',
                            $mainId,
                            $id,
                            $type,
                            $ts->format('Y-m-d H:i'),
                            $lastTs->format('Y-m-d H:i'),
                            $entries[$i - 1]->getId()
                        ),
                    ];
                }

                if ($type === 'checkout') {
                    $checkoutCount++;
                    if ($checkoutCount > 1) {
                        $errors[] = [
                            'shift' => $mainId,
                            'id' => $id,
                            'type' => 'multiple_checkouts',
                            'msg' => sprintf('Shift %d: more than one checkout (id=%d)', $mainId, $id),
                        ];
                    }
                    if ($openPause) {
                        $errors[] = [
                            'shift' => $mainId,
                            'id' => $id,
                            'type' => 'checkout_with_open_pause',
                            'msg' => sprintf('Shift %d: checkout id=%d but previous pause has no following checkin', $mainId, $id),
                        ];
                    }
                }

                if ($type === 'pause') {
                    if ($openPause) {
                        $errors[] = [
                            'shift' => $mainId,
                            'id' => $id,
                            'type' => 'pause_without_checkin',
                            'msg' => sprintf('Shift %d: two pauses in a row (id=%d), previous pause not closed by checkin', $mainId, $id),
                        ];
                    }
                    $openPause = true;
                }

                if ($type === 'checkin' && $openPause) {
                    $openPause = false;
                }

                $lastTs = $ts;
                $lastType = $type;
            }

            if ($checkoutCount === 0) {
                $errors[] = [
                    'shift' => $mainId,
                    'type' => 'missing_checkout',
                    'msg' => sprintf('Shift %d: no checkout (open shift)', $mainId),
                ];
            }
        }

        if (empty($errors)) {
            $io->success('Keine Inkonsistenzen gefunden.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d Inkonsistenz(en) gefunden:', count($errors)));
        foreach ($errors as $e) {
            $io->writeln('  [' . $e['type'] . '] ' . $e['msg']);
        }

        $byType = [];
        foreach ($errors as $e) {
            $t = $e['type'];
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }
        $io->section('Nach Typ');
        foreach ($byType as $type => $count) {
            $io->writeln(sprintf('  %s: %d', $type, $count));
        }

        return Command::FAILURE;
    }
}
