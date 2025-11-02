<?php

namespace App\Provider;

use App\Entity\Employee;
use App\Entity\User;
use App\Repository\EmployeeRepository;
use Exception;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

readonly class EmployeeProvider
{

    public function __construct(public EmployeeRepository $employeeRepository, private SerializerInterface $serializer)
    {
    }

    public function getAllEmployees(): array
    {
        $allEmployees = $this->employeeRepository->findAll();

        //to json
        $employeeArray = [];
        foreach ($allEmployees as $employee) {
            $employeeArray[] = $this->formatEmployeeToArray($employee);
        }

        return $employeeArray;
    }

    private function formatEmployeeToArray(Employee $employee): array
    {
        return $this->serializer->normalize($employee, null, [
            'groups' => ['employee:read']]);

    }

    public function getEmployeeById(int $employeeId)
    {

        $employee = $this->employeeRepository->find($employeeId);

        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        return $this->formatEmployeeToArray($employee);
    }
}
