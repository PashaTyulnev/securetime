<?php

namespace App\Service;

use App\Entity\Absence;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Exception\AbsenceException;
use App\Repository\AbsenceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class AbsenceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AbsenceRepository $absenceRepository,
    ) {}

    public function createAbsence(
        Employee $employee,
        AbsenceType $type,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?string $reason = null
    ): Absence {
        if ($startDate > $endDate) {
            throw AbsenceException::dateRangeInvalid();
        }

        $overlapping = $this->absenceRepository->findOverlapping($employee, $startDate, $endDate);
        if (count($overlapping) > 0) {
            throw AbsenceException::overlappingAbsenceExists();
        }

        $absence = new Absence();
        $absence->setEmployee($employee);
        $absence->setType($type);
        $absence->setStatus(AbsenceStatus::Pending);
        $absence->setStartDate($startDate);
        $absence->setEndDate($endDate);
        $absence->setReason($reason);

        $this->entityManager->persist($absence);
        $this->entityManager->flush();

        return $absence;
    }

    public function approveAbsence(
        Absence $absence,
        User $approvedBy,
        ?string $adminComment = null
    ): void {
        if ($absence->getStatus() !== AbsenceStatus::Pending) {
            throw AbsenceException::cannotApproveNonPending();
        }

        $absence->setStatus(AbsenceStatus::Approved);
        $absence->setApprovedBy($approvedBy);
        $absence->setApprovedAt(new DateTimeImmutable());
        $absence->setAdminComment($adminComment);

        $this->entityManager->flush();
    }

    public function rejectAbsence(
        Absence $absence,
        User $rejectedBy,
        ?string $adminComment = null
    ): void {
        if ($absence->getStatus() !== AbsenceStatus::Pending) {
            throw AbsenceException::cannotRejectNonPending();
        }

        $absence->setStatus(AbsenceStatus::Rejected);
        $absence->setRejectedBy($rejectedBy);
        $absence->setRejectedAt(new DateTimeImmutable());
        $absence->setAdminComment($adminComment);

        $this->entityManager->flush();
    }
}
