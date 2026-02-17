<?php

declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\TimeTracker;
use App\Repository\TimeTrackerRepository;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Enforces monotonic timestamps per shift: no entry may have a timestamp
 * before the previous entry (by id). Prevents "checkout before checkin" inconsistencies.
 */
class TimeTrackerEntityListener
{
    public function __construct(
        private readonly TimeTrackerRepository $timeTrackerRepository
    ) {
    }

    public function prePersist(TimeTracker $entity): void
    {
        if ($entity->getTimestamp() === null) {
            return;
        }

        $mainCheckin = $entity->getParent() ?? $entity;
        if ($entity->getParent() === null) {
            return;
        }

        $shiftEntries = $this->timeTrackerRepository->findShiftEntries($mainCheckin);
        if ($shiftEntries === []) {
            return;
        }

        $maxTs = null;
        foreach ($shiftEntries as $entry) {
            $ts = $entry->getTimestamp();
            if ($ts !== null && ($maxTs === null || $ts > $maxTs)) {
                $maxTs = $ts;
            }
        }

        if ($maxTs !== null && $entity->getTimestamp() < $maxTs) {
            throw new \DomainException(sprintf(
                'TimeTracker: Neuer Eintrag darf nicht vor dem letzten Eintrag der Schicht liegen (letzter: %s, neu: %s).',
                $maxTs->format('d.m.Y H:i'),
                $entity->getTimestamp()->format('d.m.Y H:i')
            ));
        }
    }

    public function preUpdate(TimeTracker $entity, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('timestamp')) {
            return;
        }

        $mainCheckin = $entity->getParent() ?? $entity;
        $shiftEntries = $this->timeTrackerRepository->findShiftEntries($mainCheckin);
        if (count($shiftEntries) < 2) {
            return;
        }

        usort($shiftEntries, static fn (TimeTracker $a, TimeTracker $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));

        for ($i = 0; $i < count($shiftEntries) - 1; $i++) {
            $curr = $shiftEntries[$i]->getTimestamp();
            $next = $shiftEntries[$i + 1]->getTimestamp();
            if ($curr === null || $next === null) {
                continue;
            }
            if ($next < $curr) {
                throw new \DomainException(sprintf(
                    'TimeTracker: Chronologie pro Schicht darf nicht verletzt werden. Eintrag id=%s hat Zeit %s, Nachfolger id=%s hat Zeit %s.',
                    $shiftEntries[$i]->getId(),
                    $curr->format('d.m.Y H:i'),
                    $shiftEntries[$i + 1]->getId(),
                    $next->format('d.m.Y H:i')
                ));
            }
        }
    }
}
