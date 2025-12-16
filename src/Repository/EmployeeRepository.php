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

    public function findAll(bool $withInactive = false): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.qualification', 'q')
            ->leftJoin('e.user', 'u')
            ->addSelect('q', 'u')
            ->orderBy('q.rank', 'DESC')
            ->addOrderBy('e.lastName', 'ASC');

        if (!$withInactive) {
            $qb->andWhere('u.active = :active')
                ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }


}
