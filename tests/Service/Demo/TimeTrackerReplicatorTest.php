<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo;

use App\Entity\TimeTracker;
use App\Service\Demo\TimeTrackerReplicator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the cyclic replication of TimeTracker check-in groups.
 */
class TimeTrackerReplicatorTest extends TestCase
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';

    /**
     * A check-in group (main + child) is replicated to the matching target week keeping its children.
     */
    public function testBuildPlanKeepsGroupAndShiftsDates(): void
    {
        $main = $this->createEntry('2026-04-27 08:00:00', null);
        $child = $this->createEntry('2026-04-27 16:00:00', $main);

        $plan = (new TimeTrackerReplicator())->buildPlan(
            [$main, $child],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-05-17'),
            new \DateTimeImmutable('2026-12-31')
        );

        self::assertCount(1, $plan);
        self::assertCount(2, $plan[0]['entries']);
        self::assertSame('2026-05-11', $plan[0]['targetMainDate']->format('Y-m-d'));
        self::assertSame(14, $plan[0]['deltaDays']);
        self::assertFalse($plan[0]['isFuture']);
    }

    /**
     * Target main dates after "today" are flagged as future so the command can skip them.
     */
    public function testBuildPlanFlagsFutureGroups(): void
    {
        $main = $this->createEntry('2026-05-04 08:00:00', null);

        $plan = (new TimeTrackerReplicator())->buildPlan(
            [$main],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-06-30'),
            new \DateTimeImmutable('2026-05-20')
        );

        self::assertNotEmpty($plan);
        self::assertSame('2026-05-18', $plan[0]['targetMainDate']->format('Y-m-d'));
        self::assertFalse($plan[0]['isFuture']);
        self::assertTrue($plan[1]['isFuture']);
    }

    /**
     * Children whose parent lies outside the template window are dropped as orphans.
     */
    public function testBuildPlanDropsOrphanChildren(): void
    {
        $orphanParent = $this->createEntry('2026-04-20 08:00:00', null);
        $orphanChild = $this->createEntry('2026-04-27 06:00:00', $orphanParent);

        $plan = (new TimeTrackerReplicator())->buildPlan(
            [$orphanChild],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-05-24'),
            new \DateTimeImmutable('2026-12-31')
        );

        self::assertSame([], $plan);
    }

    private function createEntry(string $timestamp, ?TimeTracker $parent): TimeTracker
    {
        $entry = new TimeTracker();
        $entry->setTimestamp(new \DateTimeImmutable($timestamp));
        $entry->setCreatedAt(new \DateTimeImmutable($timestamp));
        $entry->setType('checkin');
        $entry->setShiftStart($parent === null);
        $entry->setParent($parent);

        return $entry;
    }
}
