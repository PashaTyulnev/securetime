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
            ->leftJoin('e.user', 'u')
            ->addSelect('q')
            ->addSelect('u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->orderBy('q.rank', 'DESC')
            ->addOrderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
