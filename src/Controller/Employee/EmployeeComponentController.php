<?php

namespace App\Controller\Employee;

use App\Provider\EmployeeProvider;
use App\Repository\DepartmentRepository;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/employee')]
class EmployeeComponentController extends AbstractController
{
    public function __construct(readonly EmployeeDataApiController $employeeDataApiController,
                                readonly DepartmentRepository $departmentRepository)
    {

    }
    #[Route(path: '/all', name: 'all_employee_list_component', methods: ['GET'])]
    public function getEmployeeListComponent(): Response
    {
        //call function from other controller
        $allEmployees = $this->employeeDataApiController->getAllEmployees()->getContent();

        //to array
        $allEmployees = json_decode($allEmployees, true);
        return $this->render('employee/employee_list.html.twig', [
            'employees' => $allEmployees
        ]);
    }

    #[Route(path: '/new', name: 'new_employee_component', methods: ['GET'])]
    public function getNewEmployeeModalComponent(): Response
    {
        $departments = $this->departmentRepository->findAllSortedByPosition();

        return $this->render('employee/employee_modal.html.twig', [
            'departments' => $departments
        ]);
    }

    #[Route(path: '/edit/{employeeId}', name: 'edit_employee_component', methods: ['GET'])]
    public function getEditEmployeeModalComponent($employeeId): Response
    {
        $departments = $this->departmentRepository->findAllSortedByPosition();

        $employee = $this->employeeDataApiController->getEmployeeById($employeeId)->getContent();

        return $this->render('employee/employee_modal.html.twig', [
            'departments' => $departments,
            'employee' => json_decode($employee, true)
        ]);
    }
}
