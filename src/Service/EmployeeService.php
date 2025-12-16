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
    public function getAllEmployees($withInactive = false)
    {
        //call function from other controller
        $allEmployees = $this->employeeRepository->findAll($withInactive);

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

    public function assignPlannedHoursToEmployees(mixed $employees, array $plannedHoursData)
    {
        // 1️⃣ Lookup-Tabelle nach Employee-ID
        $plannedHoursByEmployeeId = [];

        foreach ($plannedHoursData as $data) {
            $plannedHoursByEmployeeId[$data['id']] = [
                'total' => $data['workingHoursTotal'],
                'regular' => $data['workingHoursRegular'],
                'onCall' => $data['workingHoursOnCall'],
            ];
        }

        // 2️⃣ Employees erweitern
        foreach ($employees as &$employee) {
            $employeeId = $employee['id'];

            $employee['plannedHours'] = $plannedHoursByEmployeeId[$employeeId] ?? [
                'total' => 0,
                'regular' => 0,
                'onCall' => 0,
            ];
        }

        unset($employee); // Referenz sauber lösen

        return $employees;
    }
}
