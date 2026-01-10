<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\FacilityRepository;
use App\Repository\QualificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

class EmployeeService
{
    public function __construct(readonly EmployeeRepository $employeeRepository,
                                readonly QualificationRepository $qualificationRepository,
                                readonly Security $security,
                                readonly SerializerInterface $serializer)
    {

    }
    public function getAllEmployees($withInactive = false)
    {
        $currentUser = $this->security->getUser();
        $partnerCompany = $currentUser->getEmployee()->getPartnerCompany();

        //call function from other controller
        $allEmployees = $this->employeeRepository->findAll($withInactive,$partnerCompany);

        $allEmployees = $this->serializer->serialize(
            $allEmployees,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        return json_decode($allEmployees, true);
    }

    public function getAllEmployeeEntities(bool $withInactive = false): array
    {
        $currentUser = $this->security->getUser();
        $partnerCompany = $currentUser->getEmployee()->getPartnerCompany();

        return $this->employeeRepository->findAll($withInactive, $partnerCompany);
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

    public function filterOnlyShiftedEmployees(mixed $employees, array $shifts): array
    {
        $displayEmployees = [];
        $relevantEmployeeUris = [];
        foreach ($shifts as $shift){
            $employeeUri = $shift['employee'];
            $relevantEmployeeUris[] = $employeeUri;
        }

        //remove duplicates
        $relevantEmployeeUris = array_unique($relevantEmployeeUris);

        foreach ($employees as $employee){
            if(in_array($employee['@id'], $relevantEmployeeUris)){
                $displayEmployees[] = $employee;
            }
        }

        return $displayEmployees;
    }
}
