<?php

namespace App\Repository;

use App\Entity\Shift;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shift>
 */
class ShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shift::class);
    }

    public function findShiftsInDateRange(\DateTime|false $firstDate, \DateTime|false $lastDate)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.date >= :firstDate')
            ->andWhere('s.date <= :lastDate')
            ->setParameter('firstDate', $firstDate)
            ->setParameter('lastDate', $lastDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByEmployeeAndDate(?\App\Entity\Employee $employee, ?\DateTime $getDate)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.employee = :employee')
            ->andWhere('s.date = :date')
            ->setParameter('employee', $employee)
            ->setParameter('date', $getDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
