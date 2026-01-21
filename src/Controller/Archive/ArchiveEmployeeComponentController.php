<?php

namespace App\Controller\Archive;

use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/archive/employee')]
class ArchiveEmployeeComponentController extends AbstractController
{
    public function __construct(readonly EmployeeService $employeeService)
    {
    }

    #[Route(path: '/list', name: 'archive_employee_list_component', methods: ['GET'])]
    public function getArchiveEmployeeListComponent(): Response
    {
        $inactiveEmployees = $this->employeeService->getInactiveEmployees();

        return $this->render('pages/archive/archive_employee_list.html.twig', [
            'employees' => $inactiveEmployees
        ]);
    }
}
