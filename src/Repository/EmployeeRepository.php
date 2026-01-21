<?php

namespace App\Repository;

use App\Entity\Employee;
use App\Entity\PartnerCompany;
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

    public function findAll(bool $withInactive = false, ?PartnerCompany $partnerCompany = null): array
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

        if ($partnerCompany) {
            $qb->andWhere('e.partnerCompany = :partner')
                ->setParameter('partner', $partnerCompany);
        }

        return $qb->getQuery()->getResult();
    }

    public function findInactive(?PartnerCompany $partnerCompany = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.qualification', 'q')
            ->leftJoin('e.user', 'u')
            ->addSelect('q', 'u')
            ->where('u.active = :active')
            ->setParameter('active', false)
            ->orderBy('q.rank', 'DESC')
            ->addOrderBy('e.lastName', 'ASC');

        if ($partnerCompany) {
            $qb->andWhere('e.partnerCompany = :partner')
                ->setParameter('partner', $partnerCompany);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByUsername(string $username): ?Employee
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->andWhere('u.username = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
