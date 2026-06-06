<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo;

use App\Entity\Shift;
use App\Service\Demo\ShiftReplicator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the cyclic weekly replication plan of reference shifts.
 */
class ShiftReplicatorTest extends TestCase
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';

    /**
     * Two template weeks alternate weekly and keep the weekday offset within the week.
     */
    public function testBuildPlanAlternatesWeeksAndKeepsDayOffset(): void
    {
        $weekAMonday = $this->createShift('2026-04-27', 'A-Mon');
        $weekAWednesday = $this->createShift('2026-04-29', 'A-Wed');
        $weekBMonday = $this->createShift('2026-05-04', 'B-Mon');

        $plan = (new ShiftReplicator())->buildPlan(
            [$weekAMonday, $weekAWednesday, $weekBMonday],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-05-25')
        );

        self::assertCount(3, $plan);
        self::assertSame('2026-05-11', $plan[0]['date']->format('Y-m-d'));
        self::assertSame('A-Mon', $plan[0]['template']->getNote());
        self::assertSame('2026-05-13', $plan[1]['date']->format('Y-m-d'));
        self::assertSame('A-Wed', $plan[1]['template']->getNote());
        self::assertSame('2026-05-18', $plan[2]['date']->format('Y-m-d'));
        self::assertSame('B-Mon', $plan[2]['template']->getNote());
    }

    /**
     * Without template shifts the plan is empty.
     */
    public function testBuildPlanWithoutTemplatesReturnsEmpty(): void
    {
        $plan = (new ShiftReplicator())->buildPlan(
            [],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-11-11')
        );

        self::assertSame([], $plan);
    }

    private function createShift(string $date, string $note): Shift
    {
        $shift = new Shift();
        $shift->setDate(new \DateTime($date));
        $shift->setNote($note);

        return $shift;
    }
}
