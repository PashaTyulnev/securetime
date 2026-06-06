<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Entity\Shift;

/**
 * Maps reference Shifts of a fixed template window to their target dates by repeating
 * the multi-week pattern cyclically. Pure scheduling logic without any persistence.
 */
final class ShiftReplicator
{
    private const DAYS_PER_WEEK = 7;

    /**
     * Builds the replication plan: which template shift is copied to which target date.
     *
     * @param Shift[] $templateShifts Reference shifts located inside the template window
     *
     * @return array<int, array{template: Shift, date: \DateTimeImmutable}>
     */
    public function buildPlan(
        array $templateShifts,
        \DateTimeImmutable $templateStart,
        \DateTimeImmutable $templateEnd,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): array {
        $cycleWeeks = $this->countTemplateWeeks($templateStart, $templateEnd);
        $weekGroups = $this->groupByWeek($templateShifts, $templateStart, $cycleWeeks);

        if ($weekGroups === []) {
            return [];
        }

        $plan = [];
        $weekIndex = 0;
        for ($weekStart = $from; $weekStart < $until; $weekStart = $weekStart->modify('+7 days')) {
            foreach ($weekGroups[$weekIndex % $cycleWeeks] ?? [] as $entry) {
                $plan[] = [
                    'template' => $entry['shift'],
                    'date' => $weekStart->modify('+' . $entry['dayOffset'] . ' days'),
                ];
            }
            ++$weekIndex;
        }

        return $plan;
    }

    private function countTemplateWeeks(\DateTimeImmutable $templateStart, \DateTimeImmutable $templateEnd): int
    {
        $days = $templateStart->diff($templateEnd)->days + 1;

        return (int) ceil($days / self::DAYS_PER_WEEK);
    }

    /**
     * Groups shifts by their week offset and remembers the day offset (0-6) inside that week.
     *
     * @param Shift[] $templateShifts
     *
     * @return array<int, array<int, array{shift: Shift, dayOffset: int}>>
     */
    private function groupByWeek(array $templateShifts, \DateTimeImmutable $templateStart, int $cycleWeeks): array
    {
        $groups = [];
        foreach ($templateShifts as $shift) {
            $date = $shift->getDate();
            if ($date === null) {
                continue;
            }

            $daysSinceStart = $templateStart->diff($date)->days;
            $weekIndex = intdiv($daysSinceStart, self::DAYS_PER_WEEK);
            if ($weekIndex < 0 || $weekIndex >= $cycleWeeks) {
                continue;
            }

            $groups[$weekIndex][] = ['shift' => $shift, 'dayOffset' => $daysSinceStart % self::DAYS_PER_WEEK];
        }

        return $groups;
    }
}
