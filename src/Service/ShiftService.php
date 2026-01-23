<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\DemandShiftRepository;
use App\Repository\FacilityRepository;
use App\Repository\ShiftPresetRepository;
use App\Repository\ShiftRepository;
use DateTime;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShiftService
{
    public function __construct(public ShiftPresetRepository $presetRepository,
                                public SerializerInterface $serializer,
                                readonly ShiftRepository $shiftRepository,
                                readonly Security $security,
                                readonly EmployeeService $employeeService,
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
        $currentUser = $this->security->getUser();
        $partnerCompany = $currentUser->getEmployee()->getPartnerCompany();

        $presets = $this->demandShiftRepository->findDemandShiftsOfFacilityInDateRange($facilityId, $dateFrom, $dateTo, $partnerCompany);

        $presets = $this->serializer->serialize(
            $presets,
            'jsonld',
            ['groups' => ['demandShift:read']]
        );
        return json_decode($presets, true);
    }

    public function findShiftsForToday()
    {
        $allEmployees = $this->employeeService->getAllEmployeeEntities(true);

        return $this->shiftRepository->findShiftsForToday($allEmployees);
    }

    public function checkUserPlanForToday(?User $user): array
    {
        $todayShifts = $this->findShiftsForToday();

        if ($user) {
            foreach ($todayShifts as $shift) {
                $employee = $shift->getEmployee();
                if ($employee && $employee->getUser() && $employee->getUser()->getId() === $user->getId()) {
                    $demandShift = $shift->getDemandShift();
                    $workBeginTime = $demandShift->getTimeFrom();
                    $workBeginDate = $shift->getDate();

                    // Kombiniere Datum und Uhrzeit zu einem DateTime-Objekt
                    $workBeginDateTime = new DateTime($workBeginDate->format('Y-m-d') . ' ' . $workBeginTime->format('H:i:s'));


                    $now = new DateTime();

                    //minuten unterschied zu jetzt in minuten
                    $minutesDifference = (int) (($workBeginDateTime->getTimestamp() - $now->getTimestamp()) / 60);


                    return [
                        'minutesDifferenceToWorkBegin' => $minutesDifference*-1,
                        'workingToday' => true
                    ];
                }
            }
        }

        return [
            'minutesDifferenceToWorkBegin' => null,
            'workingToday' => false
        ];
    }

    /**
     * Findet die nächste geplante Schicht für einen Benutzer (ab heute)
     */
    public function findNextPlannedShift(?User $user): ?array
    {
        if (!$user || !$user->getEmployee()) {
            return null;
        }

        $employee = $user->getEmployee();
        $today = new DateTime('today');
        
        // Suche die nächste Schicht ab heute (maximal 30 Tage in die Zukunft)
        $endDate = (clone $today)->modify('+30 days');
        
        $shifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $today->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $now = new DateTime();
        $nextShift = null;
        $nextShiftDateTime = null;

        foreach ($shifts as $shift) {
            $demandShift = $shift->getDemandShift();
            if (!$demandShift || !$demandShift->getTimeFrom()) {
                continue;
            }

            $shiftDate = $shift->getDate();
            $shiftTime = $demandShift->getTimeFrom();
            
            // Kombiniere Datum und Uhrzeit
            $shiftDateTime = new DateTime($shiftDate->format('Y-m-d') . ' ' . $shiftTime->format('H:i:s'));

            // Nur Schichten in der Zukunft berücksichtigen
            if ($shiftDateTime > $now) {
                if ($nextShiftDateTime === null || $shiftDateTime < $nextShiftDateTime) {
                    $nextShift = $shift;
                    $nextShiftDateTime = $shiftDateTime;
                }
            }
        }

        if ($nextShift) {
            $demandShift = $nextShift->getDemandShift();
            return [
                'shift' => $nextShift,
                'dateTime' => $nextShiftDateTime,
                'facility' => $demandShift->getFacility(),
                'timeFrom' => $demandShift->getTimeFrom(),
                'timeTo' => $demandShift->getTimeTo(),
                'name' => $demandShift->getName(),
            ];
        }

        return null;
    }


}
