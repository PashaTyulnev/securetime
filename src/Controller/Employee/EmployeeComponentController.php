<?php

namespace App\Controller\Employee;

use App\Repository\DepartmentRepository;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/components/employee')]
class EmployeeComponentController extends AbstractController
{
    public function __construct(readonly SerializerInterface $serializer, readonly DepartmentRepository $departmentRepository, readonly EmployeeRepository $employeeRepository)
    {

    }
    #[Route(path: '/list', name: 'all_employee_list_component', methods: ['GET'])]
    public function getEmployeeListComponent(): Response
    {
        //call function from other controller
        $allEmployees = $this->employeeRepository->findAll();

        $allEmployees = $this->serializer->serialize(
            $allEmployees,
            'jsonld',
            ['groups' => ['employee:read']]
        );

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
    public function getEditEmployeeModalComponent(int $employeeId): Response
    {
        $departments = $this->departmentRepository->findAllSortedByPosition();


        //call function from other controller
        $employee = $this->employeeRepository->find($employeeId);

        $employee = $this->serializer->serialize(
            $employee,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        $employee = json_decode($employee, true);


        return $this->render('employee/employee_modal.html.twig', [
            'departments' => $departments,
            'employee' => $employee
        ]);
    }
}
