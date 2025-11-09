<?php

namespace App\Service;

use App\Repository\FacilityRepository;
use Symfony\Component\Serializer\SerializerInterface;

class FacilityService
{
    public function __construct(readonly FacilityRepository $facilityRepository, readonly SerializerInterface $serializer)
    {

    }
    public function getAllFacilities()
    {
        $facilities = $this->facilityRepository->findAll();

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
}
