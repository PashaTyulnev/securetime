<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DemandShift;
use App\Entity\Employee;
use App\Entity\Shift;
use App\Entity\User;
use App\Repository\ShiftRepository;
use App\Service\EmployeeService;
use App\Service\ShiftService;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für ShiftService::checkUserPlanForToday (Check-in-Fenster, Zeitzone Europe/Berlin).
 */
class ShiftServiceTest extends TestCase
{
    /**
     * Benutzer mit Schicht heute: 9 Minuten vor Schichtbeginn (08:00 Berlin) → workingToday true, -9 Minuten.
     */
    public function testCheckUserPlanForToday_withinCheckinWindow_returnsWorkingTodayAndMinutesDifference(): void
    {
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $shiftDate = new \DateTime('today', new \DateTimeZone('Europe/Berlin'));
        $workBeginTime = \DateTime::createFromFormat('H:i', '08:00', new \DateTimeZone('Europe/Berlin'));
        $shift = $this->createShift($employee, $shiftDate, $workBeginTime);

        $now = new \DateTime('today 07:51', new \DateTimeZone('Europe/Berlin'));

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')
            ->willReturn([$shift]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')
            ->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $now);

        self::assertTrue($result['workingToday']);
        self::assertSame(-9, $result['minutesDifferenceToWorkBegin']);
    }

    /**
     * Exakt Schichtbeginn (08:00) → minutesDifferenceToWorkBegin = 0.
     */
    public function testCheckUserPlanForToday_atShiftStart_returnsZeroMinutesDifference(): void
    {
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $shiftDate = new \DateTime('today', new \DateTimeZone('Europe/Berlin'));
        $workBeginTime = \DateTime::createFromFormat('H:i', '08:00', new \DateTimeZone('Europe/Berlin'));
        $shift = $this->createShift($employee, $shiftDate, $workBeginTime);

        $now = new \DateTime('today 08:00', new \DateTimeZone('Europe/Berlin'));

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([$shift]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $now);

        self::assertTrue($result['workingToday']);
        self::assertSame(0, $result['minutesDifferenceToWorkBegin']);
    }

    /**
     * 10 Minuten nach Schichtbeginn → minutesDifferenceToWorkBegin = 10 (zu spät).
     * Schicht hat Ende 16:00, damit sie um 08:10 noch als „nicht beendet“ gilt.
     */
    public function testCheckUserPlanForToday_afterShiftStart_returnsPositiveMinutesDifference(): void
    {
        $tz = new \DateTimeZone('Europe/Berlin');
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $shiftDate = new \DateTime('today', $tz);
        $shift = $this->createShiftWithTimes(
            $employee,
            $shiftDate,
            \DateTime::createFromFormat('H:i', '08:00', $tz),
            \DateTime::createFromFormat('H:i', '16:00', $tz)
        );

        $now = new \DateTime('today 08:10', $tz);

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([$shift]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $now);

        self::assertTrue($result['workingToday']);
        self::assertSame(10, $result['minutesDifferenceToWorkBegin']);
    }

    /**
     * Übergebener Zeitpunkt in UTC wird in Europe/Berlin interpretiert (Zeitzonen-Konsistenz).
     */
    public function testCheckUserPlanForToday_respectsBusinessTimezone(): void
    {
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $shiftDate = new \DateTime('today', new \DateTimeZone('Europe/Berlin'));
        $workBeginTime = \DateTime::createFromFormat('H:i', '08:00', new \DateTimeZone('Europe/Berlin'));
        $shift = $this->createShift($employee, $shiftDate, $workBeginTime);

        // 06:51 UTC = 07:51 Berlin (Winterzeit) → 9 Minuten vor Schichtbeginn
        $nowUtc = new \DateTime('today 06:51', new \DateTimeZone('UTC'));

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([$shift]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $nowUtc);

        // Service konvertiert $now nach Europe/Berlin; 06:51 UTC = 07:51 Berlin → -9
        self::assertTrue($result['workingToday']);
        self::assertSame(-9, $result['minutesDifferenceToWorkBegin']);
    }

    /**
     * User ohne Schicht heute → workingToday false, minutesDifferenceToWorkBegin null.
     */
    public function testCheckUserPlanForToday_userNotWorkingToday_returnsNotWorking(): void
    {
        $user = $this->createUser(1);
        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, null);

        self::assertFalse($result['workingToday']);
        self::assertNull($result['minutesDifferenceToWorkBegin']);
    }

    /**
     * Null-User → workingToday false.
     */
    public function testCheckUserPlanForToday_nullUser_returnsNotWorking(): void
    {
        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday(null, null);

        self::assertFalse($result['workingToday']);
        self::assertNull($result['minutesDifferenceToWorkBegin']);
    }

    /**
     * Zwei Schichten heute (06:00–14:00 und 18:00–22:00): Nach erstem Schichtende wird die zweite
     * Schicht berücksichtigt. Um 17:50 → 10 Minuten vor zweiter Schicht → minutesDifferenceToWorkBegin = -10.
     */
    public function testCheckUserPlanForToday_twoShiftsToday_returnsSecondShiftWhenFirstEnded(): void
    {
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $tz = new \DateTimeZone('Europe/Berlin');
        $shiftDate = new \DateTime('today', $tz);

        $shift1 = $this->createShiftWithTimes(
            $employee,
            $shiftDate,
            \DateTime::createFromFormat('H:i', '06:00', $tz),
            \DateTime::createFromFormat('H:i', '14:00', $tz)
        );
        $shift2 = $this->createShiftWithTimes(
            $employee,
            $shiftDate,
            \DateTime::createFromFormat('H:i', '18:00', $tz),
            \DateTime::createFromFormat('H:i', '22:00', $tz)
        );

        $now = new \DateTime('today 17:50', $tz);

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([$shift1, $shift2]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $now);

        self::assertTrue($result['workingToday']);
        self::assertSame(-10, $result['minutesDifferenceToWorkBegin']);
    }

    /**
     * Zwei Schichten heute: Wenn beide beendet sind (now nach Ende der zweiten), workingToday true, minutesDifferenceToWorkBegin null.
     */
    public function testCheckUserPlanForToday_twoShiftsToday_bothEnded_returnsWorkingTodayTrueMinutesNull(): void
    {
        $user = $this->createUser(1);
        $employee = $this->createEmployee($user);
        $tz = new \DateTimeZone('Europe/Berlin');
        $shiftDate = new \DateTime('today', $tz);

        $shift1 = $this->createShiftWithTimes(
            $employee,
            $shiftDate,
            \DateTime::createFromFormat('H:i', '06:00', $tz),
            \DateTime::createFromFormat('H:i', '14:00', $tz)
        );
        $shift2 = $this->createShiftWithTimes(
            $employee,
            $shiftDate,
            \DateTime::createFromFormat('H:i', '18:00', $tz),
            \DateTime::createFromFormat('H:i', '22:00', $tz)
        );

        $now = new \DateTime('today 22:30', $tz);

        $shiftRepository = $this->createMock(ShiftRepository::class);
        $shiftRepository->method('findShiftsForToday')->willReturn([$shift1, $shift2]);

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService->method('getAllEmployeeEntities')->willReturn([$employee]);

        $service = $this->createShiftService($shiftRepository, $employeeService);
        $result = $service->checkUserPlanForToday($user, $now);

        self::assertTrue($result['workingToday']);
        self::assertNull($result['minutesDifferenceToWorkBegin']);
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionClass(User::class);
        $prop = $ref->getProperty('id');
        $prop->setValue($user, $id);
        return $user;
    }

    private function createEmployee(User $user): Employee
    {
        $employee = new Employee();
        $employee->setUser($user);
        return $employee;
    }

    private function createShift(Employee $employee, \DateTime $shiftDate, \DateTime $workBeginTime): Shift
    {
        $demandShift = new DemandShift();
        $demandShift->setTimeFrom($workBeginTime);
        $demandShift->setTimeTo(clone $workBeginTime);

        $shift = new Shift();
        $shift->setEmployee($employee);
        $shift->setDate($shiftDate);
        $shift->setDemandShift($demandShift);

        return $shift;
    }

    /**
     * Schicht mit explizitem Beginn und Ende (z. B. 06:00–14:00 und 18:00–22:00 am selben Tag).
     */
    private function createShiftWithTimes(Employee $employee, \DateTime $shiftDate, \DateTime $workBeginTime, \DateTime $workEndTime): Shift
    {
        $demandShift = new DemandShift();
        $demandShift->setTimeFrom($workBeginTime);
        $demandShift->setTimeTo($workEndTime);

        $shift = new Shift();
        $shift->setEmployee($employee);
        $shift->setDate($shiftDate);
        $shift->setDemandShift($demandShift);

        return $shift;
    }

    private function createShiftService(ShiftRepository $shiftRepository, EmployeeService $employeeService): ShiftService
    {
        return new ShiftService(
            $this->createStub(\App\Repository\ShiftPresetRepository::class),
            $this->createStub(\App\Repository\DemandShiftRepository::class),
            $this->createStub(\Symfony\Component\Serializer\SerializerInterface::class),
            $shiftRepository,
            $this->createStub(\Symfony\Bundle\SecurityBundle\Security::class),
            $employeeService,
            $this->createStub(\Symfony\Contracts\HttpClient\HttpClientInterface::class)
        );
    }
}
