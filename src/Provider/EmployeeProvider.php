<?php

namespace App\Provider;

use App\Entity\Employee;
use App\Entity\User;
use App\Repository\EmployeeRepository;

readonly class EmployeeProvider
{

    public function __construct(public EmployeeRepository $employeeRepository)
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
        return [
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'birthDate' => $employee->getBirthDate(),
            'phone' => $employee->getPhone(),
            'user' => $this->formatUserToArray($employee->getUser()),
            'number' => $employee->getNumber(),
        ];
    }

    private function formatUserToArray(User $user): array
    {
        $roles = $this->formatRolesToString($user->getRoles());

        //Position basierend auf Rolle
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'position' => $roles,
            'active' => $user->isActive(),
        ];
    }

    private function formatRolesToString(array $getRoles): ?string
    {
        foreach ($getRoles as $role) {
            if($role === 'ROLE_ADMIN') {
                return 'Administrator';
            } else {
                return 'Angestellter';
            }
        }

        return null;
    }
}
