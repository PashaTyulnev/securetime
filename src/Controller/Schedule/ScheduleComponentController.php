<?php

namespace App\Controller\Schedule;

use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ScheduleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ScheduleComponentController extends AbstractController
{

    public function __construct(readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ScheduleService $scheduleService)
    {

    }
    #[Route(path: '/components/schedule-week' , name: 'schedule_week_component', methods: ['GET'])]
    public function getScheduleWeekComponent(Request $request) : Response
    {

        $year = $request->query->get('year');
        $week = $request->query->get('week');

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildWeekDaysRange((int)$year, (int)$week);

        return $this->render('pages/schedule/schedule_week/schedule_week_overview.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
        ]);
    }

    #[Route(path: '/components/schedule/demand-shifts' , name: 'schedule_demand_shifts_component', methods: ['GET'])]
    public function getDemandShiftsOfFacilityComponent(Request $request) : Response
    {

        $facilityId = $request->query->get('facilityId');
        $shifts = $this->facilityService->getDemandShiftsOfFacility($facilityId);

        return $this->render('/pages/schedule/components/demand_shifts.html.twig', [
            'demandShifts' => $shifts,
        ]);
    }
}
