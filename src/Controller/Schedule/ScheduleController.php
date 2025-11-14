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
    public function loadIndexPage(Security $security): Response
    {
        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        return $this->render('pages/schedule/schedule_week/schedule_week_index.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities
        ]);
    }
}
