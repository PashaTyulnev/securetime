<?php

namespace App\Repository;

use App\Entity\DemandShift;
use App\Entity\Employee;
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

    public function findShiftsInDateRange(
        \DateTime|false $firstDate,
        \DateTime|false $lastDate,
        ?int $facilityId = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.demandShift', 'ds')
            ->leftJoin('ds.facility', 'f')
            ->andWhere('s.date >= :firstDate')
            ->andWhere('s.date <= :lastDate')
            ->setParameter('firstDate', $firstDate)
            ->setParameter('lastDate', $lastDate)
            ->orderBy('s.date', 'ASC');

        if ($facilityId !== null) {
            $qb->andWhere('f.id = :facilityId')
                ->setParameter('facilityId', $facilityId);
        }

        return $qb->getQuery()->getResult();
    }


    public function findByEmployeeAndDate(?Employee $employee, ?\DateTime $getDate)
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

    public function findByDemandShiftAndDate(DemandShift $demandShift, \DateTime $date): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.demandShift = :demandShift')
            ->andWhere('s.date = :date')
            ->setParameter('demandShift', $demandShift)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function getAllAssignedShiftsInRange(Employee $getEmployee, string $startDate, string $endDate)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.employee = :employee')
            ->andWhere('s.date BETWEEN :startDate AND :endDate')
            ->setParameter('employee', $getEmployee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Shifts for the given employees on the calendar day "today" in the given timezone.
     * Uses date string to avoid timezone conversion issues (DATE column vs. DateTime in UTC).
     *
     * @param array<int, \App\Entity\Employee> $employees
     * @param \DateTimeZone|null $timezone Zeitzone für „heute“ (z. B. Europe/Berlin), sonst Server-Zeitzone
     */
    public function findShiftsForToday(array $employees, ?\DateTimeZone $timezone = null): array
    {
        $todayDateTime = $timezone
            ? (new \DateTimeImmutable('now', $timezone))->setTime(0, 0, 0)
            : new \DateTimeImmutable('today');
        $todayString = $todayDateTime->format('Y-m-d');

        return $this->createQueryBuilder('s')
            ->leftJoin('s.employee', 'e')
            ->leftJoin('s.demandShift', 'ds')
            ->leftJoin('ds.facility', 'f')
            ->andWhere('s.date = :today')
            ->andWhere('s.employee IN (:employees)')
            ->setParameter('today', $todayString)
            ->setParameter('employees', $employees)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }


}
