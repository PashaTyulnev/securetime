<?php

namespace App\Service;

use App\Repository\FacilityRepository;
use App\Repository\ShiftPresetRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShiftService
{
    public function __construct(public ShiftPresetRepository $presetRepository, public SerializerInterface $serializer, public HttpClientInterface $httpClient)
    {

    }
    public function getAllShiftPresets()
    {
        $presets = $this->presetRepository->findAll();

        $presets = $this->serializer->serialize(
            $presets,
            'jsonld',
            ['groups' => ['shiftPreset:read']]
        );

        return json_decode($presets, true);
    }
}
