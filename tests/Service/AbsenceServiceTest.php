<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Absence;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Exception\AbsenceException;
use App\Repository\AbsenceRepository;
use App\Service\AbsenceService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AbsenceServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private AbsenceRepository $absenceRepository;
    private AbsenceService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->absenceRepository = $this->createMock(AbsenceRepository::class);
        $this->service = new AbsenceService($this->entityManager, $this->absenceRepository);
    }

    public function testCreateAbsence_validInput_returnsPersistedAbsence(): void
    {
        $employee = new Employee();
        $start = new DateTimeImmutable('2026-06-01');
        $end   = new DateTimeImmutable('2026-06-07');

        $this->absenceRepository->method('findOverlapping')->willReturn([]);
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Absence::class));
        $this->entityManager->expects(self::once())->method('flush');

        $absence = $this->service->createAbsence($employee, AbsenceType::Vacation, $start, $end, 'Sommerurlaub');

        self::assertInstanceOf(Absence::class, $absence);
        self::assertSame($employee, $absence->getEmployee());
        self::assertSame(AbsenceType::Vacation, $absence->getType());
        self::assertSame(AbsenceStatus::Pending, $absence->getStatus());
        self::assertSame($start, $absence->getStartDate());
        self::assertSame($end, $absence->getEndDate());
        self::assertSame('Sommerurlaub', $absence->getReason());
    }

    public function testCreateAbsence_startAfterEnd_throwsException(): void
    {
        $employee = new Employee();
        $start = new DateTimeImmutable('2026-06-10');
        $end   = new DateTimeImmutable('2026-06-05');

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AbsenceException::class);
        $this->expectExceptionMessage('Das Startdatum darf nicht nach dem Enddatum liegen.');

        $this->service->createAbsence($employee, AbsenceType::Vacation, $start, $end);
    }

    public function testCreateAbsence_overlappingAbsenceExists_throwsException(): void
    {
        $employee = new Employee();
        $start = new DateTimeImmutable('2026-06-01');
        $end   = new DateTimeImmutable('2026-06-07');

        $this->absenceRepository->method('findOverlapping')->willReturn([new Absence()]);
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AbsenceException::class);
        $this->expectExceptionMessage('Im gewählten Zeitraum existiert bereits eine offene oder genehmigte Abwesenheit');

        $this->service->createAbsence($employee, AbsenceType::Sick, $start, $end);
    }

    public function testApproveAbsence_pendingAbsence_setsStatusAndMeta(): void
    {
        $admin = new User();
        $absence = new Absence();
        // Status ist standardmäßig Pending

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->approveAbsence($absence, $admin, 'Genehmigt');

        self::assertSame(AbsenceStatus::Approved, $absence->getStatus());
        self::assertSame($admin, $absence->getApprovedBy());
        self::assertNotNull($absence->getApprovedAt());
        self::assertSame('Genehmigt', $absence->getAdminComment());
        // rejected_by und rejected_at dürfen nicht gesetzt sein
        self::assertNull($absence->getRejectedBy());
        self::assertNull($absence->getRejectedAt());
    }

    public function testApproveAbsence_nonPendingAbsence_throwsException(): void
    {
        $admin = new User();
        $absence = new Absence();
        $absence->setStatus(AbsenceStatus::Approved);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AbsenceException::class);
        $this->expectExceptionMessage('Nur Abwesenheiten mit Status „Offen" können genehmigt werden.');

        $this->service->approveAbsence($absence, $admin);
    }

    public function testRejectAbsence_pendingAbsence_setsStatusAndMeta(): void
    {
        $admin = new User();
        $absence = new Absence();
        // Status ist standardmäßig Pending

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->rejectAbsence($absence, $admin, 'Nicht möglich');

        self::assertSame(AbsenceStatus::Rejected, $absence->getStatus());
        self::assertSame($admin, $absence->getRejectedBy());
        self::assertNotNull($absence->getRejectedAt());
        self::assertSame('Nicht möglich', $absence->getAdminComment());
        // approved_by und approved_at dürfen nicht gesetzt sein
        self::assertNull($absence->getApprovedBy());
        self::assertNull($absence->getApprovedAt());
    }

    public function testRejectAbsence_nonPendingAbsence_throwsException(): void
    {
        $admin = new User();
        $absence = new Absence();
        $absence->setStatus(AbsenceStatus::Rejected);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AbsenceException::class);
        $this->expectExceptionMessage('Nur Abwesenheiten mit Status „Offen" können abgelehnt werden.');

        $this->service->rejectAbsence($absence, $admin);
    }
}
