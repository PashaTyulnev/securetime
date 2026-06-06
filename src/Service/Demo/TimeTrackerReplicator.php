<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Entity\TimeTracker;

/**
 * Replicates the reference TimeTracker groups (main check-in + its children) of a fixed
 * 2-week window cyclically into later weeks. Pure planning logic without persistence.
 *
 * A group is identified by its main check-in (parent === null). Children referencing a main
 * outside the template window are treated as orphans and dropped.
 */
final class TimeTrackerReplicator
{
    private const DAYS_PER_WEEK = 7;
    private const SECONDS_PER_DAY = 86400;

    /**
     * @param TimeTracker[] $templates Reference entries (any order)
     *
     * @return array<int, array{entries: TimeTracker[], targetMainDate: \DateTimeImmutable, deltaDays: int, isFuture: bool}>
     */
    public function buildPlan(
        array $templates,
        \DateTimeImmutable $templateStart,
        \DateTimeImmutable $templateEnd,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        \DateTimeImmutable $today
    ): array {
        $weekGroups = $this->groupByWeek($templates, $templateStart, $this->countTemplateWeeks($templateStart, $templateEnd));
        if ($weekGroups === []) {
            return [];
        }

        $cycleWeeks = $this->countTemplateWeeks($templateStart, $templateEnd);
        $plan = [];
        $weekIndex = 0;
        for ($weekStart = $from; $weekStart <= $until; $weekStart = $weekStart->modify('+7 days')) {
            foreach ($weekGroups[$weekIndex % $cycleWeeks] ?? [] as $group) {
                $this->appendPlanItem($plan, $group, $weekStart, $from, $until, $today);
            }
            ++$weekIndex;
        }

        return $plan;
    }

    /**
     * @param array<int, array{entries: TimeTracker[], main: TimeTracker, dayOffset: int}> $group
     * @param array<int, array{entries: TimeTracker[], targetMainDate: \DateTimeImmutable, deltaDays: int, isFuture: bool}> $plan
     */
    private function appendPlanItem(array &$plan, array $group, \DateTimeImmutable $weekStart, \DateTimeImmutable $from, \DateTimeImmutable $until, \DateTimeImmutable $today): void
    {
        $targetMainDate = $weekStart->modify('+' . $group['dayOffset'] . ' days');
        if ($targetMainDate < $from || $targetMainDate > $until) {
            return;
        }

        $plan[] = [
            'entries' => $group['entries'],
            'targetMainDate' => $targetMainDate,
            'deltaDays' => $this->dayDiff($this->dateOf($group['main']), $targetMainDate),
            'isFuture' => $targetMainDate > $today,
        ];
    }

    private function countTemplateWeeks(\DateTimeImmutable $templateStart, \DateTimeImmutable $templateEnd): int
    {
        return (int) ceil(($templateStart->diff($templateEnd)->days + 1) / self::DAYS_PER_WEEK);
    }

    /**
     * Builds groups (main + children) and buckets them by their template week offset.
     *
     * @param TimeTracker[] $templates
     *
     * @return array<int, array<int, array{entries: TimeTracker[], main: TimeTracker, dayOffset: int}>>
     */
    private function groupByWeek(array $templates, \DateTimeImmutable $templateStart, int $cycleWeeks): array
    {
        $groups = $this->groupByMainCheckin($templates);

        $byWeek = [];
        foreach ($groups as $group) {
            $daysSinceStart = $this->dayDiff($templateStart, $this->dateOf($group['main']));
            $weekIndex = intdiv($daysSinceStart, self::DAYS_PER_WEEK);
            if ($weekIndex < 0 || $weekIndex >= $cycleWeeks) {
                continue;
            }
            $group['dayOffset'] = $daysSinceStart % self::DAYS_PER_WEEK;
            $byWeek[$weekIndex][] = $group;
        }

        return $byWeek;
    }

    /**
     * Groups entries by their main check-in using object identity (no ids required).
     *
     * @param TimeTracker[] $templates
     *
     * @return array<int, array{entries: TimeTracker[], main: TimeTracker}>
     */
    private function groupByMainCheckin(array $templates): array
    {
        $groups = [];
        foreach ($templates as $entry) {
            if ($entry->getParent() === null) {
                $groups[spl_object_id($entry)] = ['main' => $entry, 'entries' => [$entry]];
            }
        }

        foreach ($templates as $entry) {
            $parent = $entry->getParent();
            if ($parent !== null && isset($groups[spl_object_id($parent)])) {
                $groups[spl_object_id($parent)]['entries'][] = $entry;
            }
        }

        foreach ($groups as &$group) {
            usort($group['entries'], static fn (TimeTracker $a, TimeTracker $b): int => $a->getTimestamp() <=> $b->getTimestamp());
        }

        return array_values($groups);
    }

    private function dateOf(TimeTracker $entry): \DateTimeImmutable
    {
        return $entry->getTimestamp()->setTime(0, 0, 0);
    }

    private function dayDiff(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) round(($end->getTimestamp() - $start->getTimestamp()) / self::SECONDS_PER_DAY);
    }
}
