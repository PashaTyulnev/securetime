<?php

namespace App\Controller\Schedule;

use App\Entity\Absence;
use App\Repository\AbsenceRepository;
use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\PartnerService;
use App\Service\ScheduleService;
use App\Service\ShiftService;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ScheduleComponentController extends AbstractController
{

    public function __construct(readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ShiftService $shiftService,
                                readonly PartnerService $partnerService,
                                readonly ScheduleService $scheduleService,
                                readonly Security $security,
                                readonly AbsenceRepository $absenceRepository)
    {

    }

    private function restrictToCurrentUserIfNeeded(array $employees, array $shifts): array
    {
        $roles = $this->security->getUser()?->getRoles() ?? [];
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARTNER_ADMIN', $roles, true)) {
            return [$employees, $shifts];
        }
        $iri = $this->employeeService->getCurrentEmployeeIri();
        if ($iri === null) {
            return [$employees, $shifts];
        }
        $employees = array_values(array_filter(
            $employees,
            static fn (array $e): bool => ($e['@id'] ?? null) === $iri
        ));
        $shifts = array_values(array_filter(
            $shifts,
            static fn (array $s): bool => ($s['employee'] ?? null) === $iri
        ));
        return [$employees, $shifts];
    }

    /**
     * Baut eine Lookup-Map: "{employeeId}-{Y-m-d}" → ['status' => ..., 'type' => ...]
     * Jede Abwesenheit wird auf alle betroffenen Einzeltage aufgefächert.
     */
    private function buildAbsenceMap(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $absences = $this->absenceRepository->findVisibleForSchedule($startDate, $endDate);
        $map = [];

        foreach ($absences as $absence) {
            $empId   = $absence->getEmployee()->getId();
            $current = $absence->getStartDate();
            $end     = $absence->getEndDate();

            while ($current <= $end) {
                $key = $empId . '-' . $current->format('Y-m-d');
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'status' => $absence->getStatus()->value,
                        'type'   => $absence->getType()->value,
                    ];
                }
                $current = $current->modify('+1 day');
            }
        }

        return $map;
    }

    #[Route(path: '/components/schedule-week', name: 'schedule_week_component', methods: ['GET'])]
    public function getScheduleWeekComponent(Request $request): Response
    {

        $year = $request->query->get('year');
        $week = $request->query->get('week');
        $facilityId = $request->query->get('facilityId', null);

        if($facilityId === 'null' || $facilityId === ''){
            $facilityId = null;
        }

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildWeekDaysRange((int)$year, (int)$week);

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange, $facilityId);

        [$employees, $shifts] = $this->restrictToCurrentUserIfNeeded($employees, $shifts);

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate, $facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        $absenceMap = $this->buildAbsenceMap(
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', $datesRange[0]['date'])),
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', end($datesRange)['date']))
        );

        return $this->render('pages/schedule/schedule_week/schedule_week_overview.html.twig', [
            'employees'   => $employees,
            'facilities'  => $facilities,
            'datesRange'  => $datesRange,
            'shifts'      => $shifts,
            'absenceMap'  => $absenceMap,
        ]);
    }

    #[Route(path: '/components/schedule/demand-shifts', name: 'schedule_demand_shifts_component', methods: ['GET'])]
    public function getDemandShiftsOfFacilityComponent(Request $request): Response
    {

        $facilityId = $request->query->get('facilityId');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');

        $shifts = $this->shiftService->getDemandShiftsOfFacilityInDateRange($facilityId, $dateFrom, $dateTo);

        return $this->render('/pages/schedule/components/demand_shifts.html.twig', [
            'demandShifts' => $shifts,
        ]);
    }

    #[Route(path: '/components/schedule/mini-shift-component', name: 'schedule_mini_shift_component', methods: ['POST'])]
    public function getMiniShiftComponent(Request $request): Response
    {

        $shiftData = json_decode($request->getContent(), true);

        return $this->render('pages/schedule/components/mini_shift_pill.html.twig', [
            'shift' => $shiftData,
        ]);
    }

    #[Route(path: '/components/schedule-month', name: 'schedule_month_component', methods: ['GET'])]
    public function getScheduleMonthComponent(Request $request): Response
    {

        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $facilityId = $request->query->get('facilityId', null);

        if($facilityId === 'null'){
            $facilityId = null;
        }
        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildMonthDaysRange((int)$year, (int)$month);

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange, $facilityId);

        [$employees, $shifts] = $this->restrictToCurrentUserIfNeeded($employees, $shifts);

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate, $facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        $absenceMap = $this->buildAbsenceMap(
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', $datesRange[0]['date'])),
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', end($datesRange)['date']))
        );

        return $this->render('pages/schedule/schedule_month/schedule_month_overview.html.twig', [
            'employees'          => $employees,
            'facilities'         => $facilities,
            'datesRange'         => $datesRange,
            'shifts'             => $shifts,
            'selectedFacilityId' => $facilityId,
            'absenceMap'         => $absenceMap,
        ]);
    }


    #[Route(path: '/components/schedule-two-week', name: 'schedule_two-week_component', methods: ['GET'])]
    public function getScheduleTwoWeekComponent(Request $request): Response
    {

        $year = $request->query->get('year');
        $week = $request->query->get('week');

        $facilityId = $request->query->get('facilityId', null);

        if($facilityId === 'null'){
            $facilityId = null;
        }

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildWeekDaysRange((int)$year, (int)$week, 2);
        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange, $facilityId);

        [$employees, $shifts] = $this->restrictToCurrentUserIfNeeded($employees, $shifts);

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate, $facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        $absenceMap = $this->buildAbsenceMap(
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', $datesRange[0]['date'])),
            DateTimeImmutable::createFromMutable(DateTime::createFromFormat('d.m.Y', end($datesRange)['date']))
        );

        return $this->render('pages/schedule/schedule_two_week/schedule_two_week_overview.html.twig', [
            'employees'          => $employees,
            'facilities'         => $facilities,
            'datesRange'         => $datesRange,
            'shifts'             => $shifts,
            'selectedFacilityId' => $facilityId,
            'absenceMap'         => $absenceMap,
        ]);
    }
}
