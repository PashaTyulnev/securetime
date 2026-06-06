<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo;

use App\Entity\DemandShift;
use App\Service\Demo\DemandShiftReplicator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the cyclic weekly replication of DemandShift templates.
 */
class DemandShiftReplicatorTest extends TestCase
{
    private const TEMPLATE_START = '2026-04-27';
    private const TEMPLATE_END = '2026-05-10';

    /**
     * Two template weeks replicated four weeks ahead must alternate A, B, A, B and copy all fields.
     */
    public function testReplicateAlternatesWeeklyAndCopiesFields(): void
    {
        $weekA = $this->createTemplate('2026-04-27', 'Frühdienst', 3);
        $weekB = $this->createTemplate('2026-05-04', 'Spätdienst', 5);

        $result = (new DemandShiftReplicator())->replicate(
            [$weekA, $weekB],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-06-08')
        );

        self::assertCount(4, $result);
        self::assertSame('Frühdienst', $result[0]->getName());
        self::assertSame('Spätdienst', $result[1]->getName());
        self::assertSame('Frühdienst', $result[2]->getName());
        self::assertSame('Spätdienst', $result[3]->getName());
    }

    /**
     * valid_from is set to the new week start and valid_to to start + 6 days.
     */
    public function testReplicateSetsWeekBoundaries(): void
    {
        $weekA = $this->createTemplate('2026-04-27', 'Frühdienst', 3);

        $result = (new DemandShiftReplicator())->replicate(
            [$weekA],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-05-18')
        );

        self::assertCount(1, $result);
        self::assertSame('2026-05-11', $result[0]->getValidFrom()?->format('Y-m-d'));
        self::assertSame('2026-05-17', $result[0]->getValidTo()?->format('Y-m-d'));
        self::assertTrue($result[0]->isOnMonday());
        self::assertSame(3, $result[0]->getAmountEmployees());
    }

    /**
     * No templates results in no generated rows.
     */
    public function testReplicateWithoutTemplatesReturnsEmpty(): void
    {
        $result = (new DemandShiftReplicator())->replicate(
            [],
            new \DateTimeImmutable(self::TEMPLATE_START),
            new \DateTimeImmutable(self::TEMPLATE_END),
            new \DateTimeImmutable('2026-05-11'),
            new \DateTimeImmutable('2026-11-11')
        );

        self::assertSame([], $result);
    }

    private function createTemplate(string $validFrom, string $name, int $amount): DemandShift
    {
        $template = new DemandShift();
        $template->setValidFrom(new \DateTime($validFrom));
        $template->setValidTo(new \DateTime($validFrom . ' +6 days'));
        $template->setTimeFrom(\DateTime::createFromFormat('!H:i', '08:00'));
        $template->setTimeTo(\DateTime::createFromFormat('!H:i', '16:00'));
        $template->setAmountEmployees($amount);
        $template->setOnMonday(true);
        $template->setOnTuesday(true);
        $template->setOnWednesday(true);
        $template->setOnThursday(true);
        $template->setOnFriday(true);
        $template->setOnSaturday(false);
        $template->setOnSunday(false);
        $template->setName($name);
        $template->setColor('#3498db');
        $template->setIsOnCall(false);

        return $template;
    }
}
