<?php

namespace App\Controller\Employee;

use App\Repository\DepartmentRepository;
use App\Repository\EmployeeRepository;
use App\Service\DepartmentService;
use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/components/employee')]
class EmployeeComponentController extends AbstractController
{
    public function __construct(readonly SerializerInterface  $serializer,
                                readonly DepartmentService    $departmentService,
                                readonly EmployeeService      $employeeService,
                                readonly DepartmentRepository $departmentRepository,
                                readonly EmployeeRepository   $employeeRepository)
    {

    }

    #[Route(path: '/list', name: 'all_employee_list_component', methods: ['GET'])]
    public function getEmployeeListComponent(): Response
    {
        //call function from other controller
        $allEmployees = $this->employeeService->getAllEmployees();

        return $this->render('pages/employee/employee_list.html.twig', [
            'employees' => $allEmployees
        ]);
    }

    #[Route(path: '/new', name: 'new_employee_component', methods: ['GET'])]
    public function getNewEmployeeModalComponent(): Response
    {
        $departments = $this->departmentService->getAllDepartments();
        $qualifications = $this->employeeService->getAllQualifications();
        return $this->render('pages/employee/employee_modal.html.twig', [
            'departments' => $departments,
            'qualifications' => $qualifications
        ]);
    }

    #[Route(path: '/edit/{employeeId}', name: 'edit_employee_component', methods: ['GET'])]
    public function getEditEmployeeModalComponent(int $employeeId): Response
    {
        $departments = $this->departmentService->getAllDepartments();
        $qualifications = $this->employeeService->getAllQualifications();

        //call function from other controller
        $employee = $this->employeeService->getEmployee($employeeId);

        return $this->render('pages/employee/employee_modal.html.twig', [
            'departments' => $departments,
            'employee' => $employee,
            'qualifications' => $qualifications
        ]);
    }
}
