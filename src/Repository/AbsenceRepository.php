<?php

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\Employee;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Findet überschneidende pending/approved Abwesenheiten desselben Mitarbeiters.
     *
     * @return Absence[]
     */
    public function findOverlapping(
        Employee $employee,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->setParameter('employee', $employee)
            ->setParameter('statuses', [AbsenceStatus::Pending, AbsenceStatus::Approved])
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'));

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Gefilterte Abwesenheiten mit eager-geladenem Employee (kein N+1).
     * Zeitraumfilter nutzt Überschneidungslogik: start <= filterEnd AND end >= filterStart.
     *
     * @return Absence[]
     */
    public function findFiltered(
        ?int $employeeId = null,
        ?AbsenceStatus $status = null,
        ?AbsenceType $type = null,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.employee', 'e')
            ->addSelect('e')
            ->orderBy('a.createdAt', 'DESC');

        if ($employeeId !== null) {
            $qb->andWhere('e.id = :employeeId')
               ->setParameter('employeeId', $employeeId);
        }

        if ($status !== null) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($type !== null) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }

        if ($startDate !== null) {
            $qb->andWhere('a.endDate >= :filterStart')
               ->setParameter('filterStart', $startDate->format('Y-m-d'));
        }

        if ($endDate !== null) {
            $qb->andWhere('a.startDate <= :filterEnd')
               ->setParameter('filterEnd', $endDate->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Lädt pending+approved Abwesenheiten die sich mit dem Zeitraum überschneiden
     * (für Kalenderanzeige im Schichtplan). Employee wird eager geladen (kein N+1).
     *
     * @return Absence[]
     */
    public function findVisibleForSchedule(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): array {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.employee', 'e')
            ->addSelect('e')
            ->where('a.status IN (:statuses)')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->setParameter('statuses', [AbsenceStatus::Pending, AbsenceStatus::Approved])
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
