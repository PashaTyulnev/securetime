<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\FacilityRepository;
use Symfony\Component\Serializer\SerializerInterface;

class EmployeeService
{
    public function __construct(readonly EmployeeRepository $employeeRepository, readonly SerializerInterface $serializer)
    {

    }
    public function getAllEmployees()
    {
        //call function from other controller
        $allEmployees = $this->employeeRepository->findAll();

        $allEmployees = $this->serializer->serialize(
            $allEmployees,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        return json_decode($allEmployees, true);
    }
    public function getEmployee($id)
    {
        //call function from other controller
        $employee = $this->employeeRepository->find($id);

        $employee = $this->serializer->serialize(
            $employee,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        return json_decode($employee, true);
    }
}
