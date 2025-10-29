<?php

namespace App\Controller\Employee;

use App\Provider\EmployeeProvider;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/employee')]
class EmployeeComponentController extends AbstractController
{
    public function __construct(readonly EmployeeDataApiController $employeeDataApiController)
    {

    }
    #[Route(path: '/all', name: 'all_employee_list_component', methods: ['GET'])]
    public function getEmployeeListComponent(): Response
    {
        //call function from other controller
        $allEmployees = $this->employeeDataApiController->getAllEmployees()->getContent();

        //to array
        $allEmployees = json_decode($allEmployees, true);
        return $this->render('employee/employeeList.html.twig', [
            'employees' => $allEmployees
        ]);
    }
}
