<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.qualification', 'q')
            ->addSelect('q')
            ->orderBy('q.rank', 'DESC')  // höchste Qualifikation zuerst
            ->addOrderBy('e.lastName', 'ASC')  // optional: innerhalb gleicher Rank nach Name sortieren
            ->getQuery()
            ->getResult();
    }

}
