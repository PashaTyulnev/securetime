<?php

namespace App\Controller\Schedule;

use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ScheduleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScheduleController extends AbstractController
{

    public function __construct(readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ScheduleService $scheduleService)
    {

    }
    #[Route(path: '/schedule', name: 'app_schedule')]
    public function loadWeekIndexPage(Security $security): Response
    {
        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        return $this->render('pages/schedule/schedule_week/schedule_week_index.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities
        ]);
    }

    #[Route(path: '/schedule/month', name: 'app_schedule_month')]
    public function loadMonthIndexPage(Security $security): Response
    {
        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        return $this->render('pages/schedule/schedule_month/schedule_month_index.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities
        ]);
    }
}
