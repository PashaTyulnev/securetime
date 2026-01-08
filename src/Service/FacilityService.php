<?php

namespace App\Service;

use App\Repository\FacilityRepository;
use App\Repository\ShiftRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacilityService
{
    public function __construct(readonly FacilityRepository  $facilityRepository,
                                readonly SerializerInterface $serializer,
                                readonly HttpClientInterface $httpClient,
                                readonly EmployeeService     $employeeService,
                                readonly ShiftRepository     $shiftRepository,
    )
    {

    }

    public function getAllFacilities()
    {
        $contextEmployees = $this->employeeService->getAllEmployees();
        $employeeIds = array_map(fn($emp) => $emp['id'], $contextEmployees);

        $facilities = $this->facilityRepository->findByEmployees($employeeIds);

        $facilities = $this->serializer->serialize(
            $facilities,
            'jsonld',
            ['groups' => ['facility:read']]
        );

        return json_decode($facilities, true);
    }

    public function getFacilityById(int $facility)
    {
        $facility = $this->facilityRepository->find($facility);

        $facility = $this->serializer->serialize(
            $facility,
            'jsonld',
            ['groups' => ['facility:read']]
        );

        return json_decode($facility, true);
    }

    public function getDemandShiftsOfFacility(float|bool|int|string|null $facilityId)
    {
        $facility = $this->facilityRepository->find($facilityId);

        if (!$facility) {
            return [];
        }

        $demandShifts = $facility->getDemandShifts();

        $demandShifts = $this->serializer->serialize(
            $demandShifts,
            'jsonld',
            ['groups' => ['demandShift:read']]
        );

        return json_decode($demandShifts, true);
    }

}
