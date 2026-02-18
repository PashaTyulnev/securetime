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
                                readonly ScheduleService $scheduleService,
                                readonly Security $security)
    {

    }

    private function getEmployeesForSchedule(): array
    {
        $employees = $this->employeeService->getAllEmployees();
        if ($this->isScheduleRestrictedToCurrentUser()) {
            $iri = $this->employeeService->getCurrentEmployeeIri();
            if ($iri !== null) {
                $employees = array_values(array_filter(
                    $employees,
                    static fn (array $e): bool => ($e['@id'] ?? null) === $iri
                ));
            }
        }
        return $employees;
    }

    private function isScheduleRestrictedToCurrentUser(): bool
    {
        $roles = $this->security->getUser()?->getRoles() ?? [];
        return !in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_PARTNER_ADMIN', $roles, true);
    }

    #[Route(path: '/schedule', name: 'app_schedule')]
    public function loadWeekIndexPage(Security $security): Response
    {
        return $this->render('pages/schedule/schedule_week/schedule_week_index.html.twig', [
            'employees' => $this->getEmployeesForSchedule(),
            'facilities' => $this->facilityService->getAllFacilities()
        ]);
    }

    #[Route(path: '/schedule/two-week', name: 'app_schedule_two_week')]
    public function loadTwoWeekIndexPage(Security $security): Response
    {
        return $this->render('pages/schedule/schedule_two_week/schedule_two_week_index.html.twig', [
            'employees' => $this->getEmployeesForSchedule(),
            'facilities' => $this->facilityService->getAllFacilities()
        ]);
    }

    #[Route(path: '/schedule/month', name: 'app_schedule_month')]
    public function loadMonthIndexPage(Security $security): Response
    {
        return $this->render('pages/schedule/schedule_month/schedule_month_index.html.twig', [
            'employees' => $this->getEmployeesForSchedule(),
            'facilities' => $this->facilityService->getAllFacilities()
        ]);
    }
}
