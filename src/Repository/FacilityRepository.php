<?php

namespace App\Repository;

use App\Entity\Facility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facility>
 */
class FacilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facility::class);
    }

    public function findByEmployees(array $employeeIds): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.demandShifts', 'ds')
            ->innerJoin('ds.shifts', 's')
            ->innerJoin('s.employee', 'e')
            ->where('e.id IN (:employeeIds)')
            ->setParameter('employeeIds', $employeeIds)
            ->groupBy('f.id')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithActiveFilter(?bool $active = true): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.company', 'c')
            ->addSelect('c')
            ->orderBy('f.shortTitle', 'ASC');

        if ($active !== null) {
            $qb->andWhere('f.active = :active')
                ->setParameter('active', $active);
        }

        return $qb->getQuery()->getResult();
    }
}
