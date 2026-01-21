<?php

namespace App\Repository;

use App\Entity\TimeTracker;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TimeTrackerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeTracker::class);
    }

    public function findPreviousMainCheckinEntry(User $user, int $currentEntryId): ?TimeTracker
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.id < :currentId')
            ->andWhere('t.isShiftStart = :isShiftStart')
            ->setParameter('user', $user)
            ->setParameter('currentId', $currentEntryId)
            ->setParameter('isShiftStart', true)
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextMainCheckinEntry(User $user, int $currentEntryId): ?TimeTracker
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.id > :currentId')
            ->andWhere('t.isShiftStart = :isShiftStart')
            ->setParameter('user', $user)
            ->setParameter('currentId', $currentEntryId)
            ->setParameter('isShiftStart', true)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function lastUserWorkingTimes()
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.user', 'u')
            ->where('t.timestamp = (
                SELECT MAX(t2.timestamp)
                FROM App\Entity\TimeTracker t2
                WHERE t2.user = t.user
            )')
            ->orderBy('t.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liefert alle TimeTracker-Einträge einer Schicht (Main-Checkin + Kinder) chronologisch.
     */
    public function findShiftEntries(TimeTracker $mainCheckin): array
    {
        return $this->createQueryBuilder('t')
            ->where('t = :mainCheckin OR t.parent = :mainCheckin')
            ->setParameter('mainCheckin', $mainCheckin)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
