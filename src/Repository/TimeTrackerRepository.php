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

    /**
     * Loads all reference entries whose timestamp is inside the template window (ordered chronologically).
     *
     * @return TimeTracker[]
     */
    public function findTemplatesInRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.timestamp >= :start')
            ->andWhere('t.timestamp <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts all entries on or after the given timestamp.
     */
    public function countFromTimestamp(\DateTimeInterface $from): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.timestamp >= :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
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
            ->orderBy('t.timestamp', 'ASC')
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

    /**
     * Findet alle Benutzer, die aktuell eingecheckt sind (letzter Eintrag ist 'checkin').
     * Gibt für jeden Benutzer den letzten Checkin-Eintrag zurück.
     */
    public function findCheckedInUsers(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.user', 'u')
            ->where('t.timestamp = (
                SELECT MAX(t2.timestamp)
                FROM App\Entity\TimeTracker t2
                WHERE t2.user = t.user
            )')
            ->andWhere('t.type = :checkinType')
            ->setParameter('checkinType', 'checkin')
            ->orderBy('t.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liefert alle erfassten Schicht-Zeiträume eines Benutzers (Beginn bis Ende je Schicht).
     * Offene Schichten (ohne Checkout) haben end = null.
     *
     * @return array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable|null}>
     */
    public function getTimeRangesForUser(User $user): array
    {
        $mainCheckins = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.isShiftStart = :isShiftStart')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('isShiftStart', true)
            ->setParameter('type', 'checkin')
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        $ranges = [];
        foreach ($mainCheckins as $main) {
            $checkout = $this->findOneBy(['parent' => $main, 'type' => 'checkout']);
            $ranges[] = [
                'start' => $main->getTimestamp(),
                'end' => $checkout?->getTimestamp(),
            ];
        }
        return $ranges;
    }
}
