<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Entity\DemandShift;

/**
 * Replicates a fixed template window of weekly DemandShift rules cyclically into the future.
 *
 * The template window (e.g. 27.04.2026 - 10.05.2026) is treated as N consecutive weeks.
 * Each future week reuses the rule of the matching template week (cycle = number of template weeks).
 * Weekday flags are copied verbatim because a DemandShift already is a weekly rule.
 */
final class DemandShiftReplicator
{
    private const DAYS_PER_WEEK = 7;
    private const WEEK_SPAN_DAYS = 6;

    /**
     * Builds new (unpersisted) DemandShift entities for every target week in [$from, $until).
     *
     * @param DemandShift[] $templates Reference rows located inside the template window
     *
     * @return DemandShift[] Newly created, not yet persisted clones
     */
    public function replicate(
        array $templates,
        \DateTimeImmutable $templateStart,
        \DateTimeImmutable $templateEnd,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): array {
        $cycleWeeks = $this->countTemplateWeeks($templateStart, $templateEnd);
        $weekGroups = $this->groupTemplatesByWeek($templates, $templateStart, $cycleWeeks);

        if ($weekGroups === []) {
            return [];
        }

        $result = [];
        $weekIndex = 0;
        for ($weekStart = $from; $weekStart < $until; $weekStart = $weekStart->modify('+7 days')) {
            foreach ($weekGroups[$weekIndex % $cycleWeeks] ?? [] as $template) {
                $result[] = $this->cloneForWeek($template, $weekStart);
            }
            ++$weekIndex;
        }

        return $result;
    }

    /**
     * Number of full or partial weeks contained in the inclusive template window.
     */
    private function countTemplateWeeks(\DateTimeImmutable $templateStart, \DateTimeImmutable $templateEnd): int
    {
        $days = $templateStart->diff($templateEnd)->days + 1;

        return (int) ceil($days / self::DAYS_PER_WEEK);
    }

    /**
     * Groups templates by their week offset (0-based) relative to the template start.
     *
     * @param DemandShift[] $templates
     *
     * @return array<int, DemandShift[]>
     */
    private function groupTemplatesByWeek(array $templates, \DateTimeImmutable $templateStart, int $cycleWeeks): array
    {
        $groups = [];
        foreach ($templates as $template) {
            $validFrom = $template->getValidFrom();
            if ($validFrom === null) {
                continue;
            }

            $weekIndex = intdiv($templateStart->diff($validFrom)->days, self::DAYS_PER_WEEK);
            if ($weekIndex < 0 || $weekIndex >= $cycleWeeks) {
                continue;
            }

            $groups[$weekIndex][] = $template;
        }

        return $groups;
    }

    /**
     * Creates a clone of the template with valid_from/valid_to set to the target week.
     */
    private function cloneForWeek(DemandShift $template, \DateTimeImmutable $weekStart): DemandShift
    {
        $clone = new DemandShift();
        $clone->setFacility($template->getFacility());
        $clone->setValidFrom(\DateTime::createFromInterface($weekStart));
        $clone->setValidTo(\DateTime::createFromInterface($weekStart->modify('+' . self::WEEK_SPAN_DAYS . ' days')));
        $clone->setTimeFrom(clone $template->getTimeFrom());
        $clone->setTimeTo(clone $template->getTimeTo());
        $clone->setAmountEmployees((int) $template->getAmountEmployees());
        $clone->setName((string) $template->getName());
        $clone->setColor($template->getColor());
        $clone->setRequiredQualification($template->getRequiredQualification());
        $clone->setIsOnCall((bool) $template->isOnCall());
        $this->copyWeekdayFlags($template, $clone);

        return $clone;
    }

    /**
     * Copies the weekly recurrence flags verbatim (a DemandShift is already a weekly rule).
     */
    private function copyWeekdayFlags(DemandShift $template, DemandShift $clone): void
    {
        $clone->setOnMonday((bool) $template->isOnMonday());
        $clone->setOnTuesday((bool) $template->isOnTuesday());
        $clone->setOnWednesday((bool) $template->isOnWednesday());
        $clone->setOnThursday((bool) $template->isOnThursday());
        $clone->setOnFriday((bool) $template->isOnFriday());
        $clone->setOnSaturday((bool) $template->isOnSaturday());
        $clone->setOnSunday((bool) $template->isOnSunday());
    }
}
