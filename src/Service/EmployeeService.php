<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\FacilityRepository;
use App\Repository\QualificationRepository;
use Symfony\Component\Serializer\SerializerInterface;

class EmployeeService
{
    public function __construct(readonly EmployeeRepository $employeeRepository,
                                readonly QualificationRepository $qualificationRepository,
                                readonly SerializerInterface $serializer)
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

    public function getAllQualifications()
    {
        //call function from other controller
        $allQualifications = $this->qualificationRepository->findAll();

        $allQualifications = $this->serializer->serialize(
            $allQualifications,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        return json_decode($allQualifications, true);
    }
}
