<?php

namespace App\Repository;

use App\Entity\DemandShift;
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

    //    /**
    //     * @return DemandShift[] Returns an array of DemandShift objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DemandShift
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findDemandShiftsOfFacilityInDateRange(float|bool|int|string|null $facilityId, float|bool|int|string|null $dateFrom, float|bool|int|string|null $dateTo)
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

        return $qb->getQuery()->getResult();
    }
}
