<?php

namespace App\Repository;

use App\Entity\DemandShift;
use App\Entity\PartnerCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandShift>
 */
class DemandShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandShift::class);
    }

    /**
     * Loads all reference DemandShift rows fully contained in the template window.
     *
     * @return DemandShift[]
     */
    public function findTemplatesInRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.validFrom >= :start')
            ->andWhere('d.validTo <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('d.facility', 'ASC')
            ->addOrderBy('d.validFrom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the ids of all DemandShift rows starting on or after the given date.
     *
     * @return int[]
     */
    public function findIdsFromDate(\DateTimeInterface $from): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.id')
            ->andWhere('d.validFrom >= :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Finds the generated DemandShift that matches a template DemandShift on a concrete target date.
     *
     * Matching: facility, name, time window, on-call flag, active weekday and validity range.
     */
    public function findMatchingForTemplate(DemandShift $template, \DateTimeInterface $date): ?DemandShift
    {
        $weekdayProperty = $this->weekdayProperty((int) $date->format('N'));

        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.name = :name')
            ->andWhere('d.timeFrom = :timeFrom')
            ->andWhere('d.timeTo = :timeTo')
            ->andWhere('d.isOnCall = :isOnCall')
            ->andWhere('d.validFrom <= :date')
            ->andWhere('d.validTo >= :date')
            ->andWhere(sprintf('d.%s = true', $weekdayProperty))
            ->setParameter('name', $template->getName())
            ->setParameter('timeFrom', $template->getTimeFrom(), Types::TIME_MUTABLE)
            ->setParameter('timeTo', $template->getTimeTo(), Types::TIME_MUTABLE)
            ->setParameter('isOnCall', $template->isOnCall())
            ->setParameter('date', $date, Types::DATE_MUTABLE)
            ->setMaxResults(1);

        $facility = $template->getFacility();
        $facility === null
            ? $qb->andWhere('d.facility IS NULL')
            : $qb->andWhere('d.facility = :facility')->setParameter('facility', $facility);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Maps an ISO weekday number (1=Monday .. 7=Sunday) to the matching entity property.
     */
    private function weekdayProperty(int $isoWeekday): string
    {
        return match ($isoWeekday) {
            1 => 'onMonday',
            2 => 'onTuesday',
            3 => 'onWednesday',
            4 => 'onThursday',
            5 => 'onFriday',
            6 => 'onSaturday',
            default => 'onSunday',
        };
    }

    public function findTemplateDemandShifts(int $facilityId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.facility = :facility')
            ->andWhere('d.validFrom IS NULL')
            ->andWhere('d.validTo IS NULL')
            ->setParameter('facility', $facilityId)
            ->getQuery()
            ->getResult();
    }

    public function findPeriodDemandShifts(int $facilityId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.facility = :facility')
            ->andWhere('d.validFrom IS NOT NULL OR d.validTo IS NOT NULL')
            ->setParameter('facility', $facilityId)
            ->getQuery()
            ->getResult();
    }

    public function findDemandShiftsOfFacilityInDateRange(
        float|bool|int|string|null $facilityId,
        float|bool|int|string|null $dateFrom,
        float|bool|int|string|null $dateTo,
        ?PartnerCompany $partnerCompany = null
    ): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.facility = :facilityId')
            ->setParameter('facilityId', $facilityId);

        if ($dateFrom) {
            $qb->andWhere('d.validFrom >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('d.validTo <= :dateTo')
                ->setParameter('dateTo', new \DateTime($dateTo));
        }

        if ($partnerCompany) {
            $qb->leftJoin('d.partnerVisibility', 'pv')
                ->andWhere('pv = :partner')
                ->setParameter('partner', $partnerCompany);
        }

        return $qb->getQuery()->getResult();
    }
}
