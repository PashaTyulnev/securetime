<?php

namespace App\Service;

use App\Repository\DemandShiftRepository;
use App\Repository\FacilityRepository;
use App\Repository\ShiftPresetRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShiftService
{
    public function __construct(public ShiftPresetRepository $presetRepository,
                                public SerializerInterface $serializer,
                                public DemandShiftRepository $demandShiftRepository,
                                public HttpClientInterface $httpClient)
    {

    }
    public function getAllDemandShiftPresets()
    {
        $presets = $this->presetRepository->findAll();

        $presets = $this->serializer->serialize(
            $presets,
            'jsonld',
            ['groups' => ['shiftPreset:read']]
        );

        return json_decode($presets, true);
    }

    public function getDemandShiftsOfFacilityInDateRange(float|bool|int|string|null $facilityId, float|bool|int|string|null $dateFrom, float|bool|int|string|null $dateTo)
    {
        $presets = $this->demandShiftRepository->findDemandShiftsOfFacilityInDateRange($facilityId, $dateFrom, $dateTo);
        $presets = $this->serializer->serialize(
            $presets,
            'jsonld',
            ['groups' => ['demandShift:read']]
        );
        return json_decode($presets, true);
    }
}
