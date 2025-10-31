<?php

namespace App\Controller\Employee;

use App\Processor\EmployeeProcessor;
use App\Provider\EmployeeProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employee')]
class EmployeeDataApiController extends AbstractController
{

    public function __construct(readonly EmployeeProvider $employeeProvider, readonly EmployeeProcessor $employeeProcessor)
    {
    }

    #[Route('/all', name: 'api_employee_get_all', methods: ['GET'])]
    public function getAllEmployees(): JsonResponse
    {
        $allEmployees = $this->employeeProvider->getAllEmployees();

        return new JsonResponse($allEmployees);
    }

    #[Route('/create', name: 'api_employee_create', methods: ['POST'])]
    public function createEmployee(Request $request): JsonResponse
    {
        $newEmployeeData = json_decode($request->getContent(), true);

        try {
            $this->employeeProcessor->createEmployee($newEmployeeData);
            return new JsonResponse(['status' => 'success'], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/delete/{employeeId}', name: 'api_employee_delete', methods: ['DELETE'])]
    public function deleteEmployee(int $employeeId): JsonResponse
    {
        try {
            $this->employeeProcessor->deleteEmployee($employeeId);
            return new JsonResponse(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
