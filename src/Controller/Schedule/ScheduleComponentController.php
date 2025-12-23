<?php

namespace App\Controller\Schedule;

use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ScheduleService;
use App\Service\ShiftService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ScheduleComponentController extends AbstractController
{

    public function __construct(readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ShiftService $shiftService,
                                readonly ScheduleService $scheduleService)
    {

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

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange);

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);


        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate,$facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        return $this->render('pages/schedule/schedule_week/schedule_week_overview.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
            'shifts' => $shifts,
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

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate,$facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        return $this->render('pages/schedule/schedule_month/schedule_month_overview.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
            'shifts' => $shifts,
            'selectedFacilityId' => $facilityId
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
        $datesRange = $this->scheduleService->buildWeekDaysRange((int)$year, (int)$week,2);
        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange, $facilityId);

        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $plannedHours = $this->scheduleService->getPlannedHoursForEmployeesForDateRange($firstDate, $lastDate, $facilityId);

        $employees = $this->employeeService->assignPlannedHoursToEmployees($employees, $plannedHours);

        return $this->render('pages/schedule/schedule_two_week/schedule_two_week_overview.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
            'shifts' => $shifts,
            'selectedFacilityId' => $facilityId,
        ]);
    }
}
