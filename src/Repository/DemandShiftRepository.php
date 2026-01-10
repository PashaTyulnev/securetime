<?php

namespace App\Repository;

use App\Entity\DemandShift;
use App\Entity\PartnerCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
