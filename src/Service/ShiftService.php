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
        $upcoming = $this->findUpcomingShiftsForUser($user, 30);

        if (empty($upcoming)) {
            return null;
        }

        $first = $upcoming[0];
        $demandShift = $first->getDemandShift();
        $shiftDate = $first->getDate();
        $shiftTimeFrom = $demandShift?->getTimeFrom();
        $shiftTimeTo = $demandShift?->getTimeTo();

        $shiftDateTime = null;
        if ($shiftTimeFrom !== null) {
            $shiftDateTime = new DateTime($shiftDate->format('Y-m-d') . ' ' . $shiftTimeFrom->format('H:i:s'));
        }

        return [
            'shift' => $first,
            'dateTime' => $shiftDateTime,
            'facility' => $demandShift?->getFacility(),
            'timeFrom' => $shiftTimeFrom,
            'timeTo' => $shiftTimeTo,
            'name' => $demandShift?->getName(),
        ];
    }

    /**
     * Liefert alle kommenden Schichten des Benutzers für die nächsten $days Tage (inkl. heute).
     *
     * Rückgabe: Array von Shift-Entities, nach Datum/Uhrzeit aufsteigend sortiert.
     */
    public function findUpcomingShiftsForUser(?User $user, int $days = 7): array
    {
        if ($days <= 0 || !$user || !$user->getEmployee()) {
            return [];
        }

        $employee = $user->getEmployee();
        $today = new DateTime('today');
        $endDate = (clone $today)->modify(sprintf('+%d days', $days - 1));

        $shifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $today->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // Sicherstellen, dass Schichten nach Datum/Zeit sortiert sind
        usort($shifts, static function ($a, $b): int {
            $demandA = $a->getDemandShift();
            $demandB = $b->getDemandShift();

            $dateA = $a->getDate();
            $dateB = $b->getDate();

            if ($dateA < $dateB) {
                return -1;
            }
            if ($dateA > $dateB) {
                return 1;
            }

            $timeFromA = $demandA?->getTimeFrom();
            $timeFromB = $demandB?->getTimeFrom();

            if ($timeFromA === null && $timeFromB === null) {
                return 0;
            }
            if ($timeFromA === null) {
                return 1;
            }
            if ($timeFromB === null) {
                return -1;
            }

            return $timeFromA <=> $timeFromB;
        });

        return $shifts;
    }

}
