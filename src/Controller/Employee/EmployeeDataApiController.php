<?php

namespace App\Controller\Employee;

use App\Provider\EmployeeProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employee')]
class EmployeeDataApiController extends AbstractController
{

    public function __construct(readonly EmployeeProvider $employeeProvider)
    {
    }

    #[Route('/all', name: 'api_employee_get_all', methods: ['GET'])]
    public function getAllEmployees(): JsonResponse
    {
        $allEmployees = $this->employeeProvider->getAllEmployees();

        return new JsonResponse($allEmployees);
    }
}
