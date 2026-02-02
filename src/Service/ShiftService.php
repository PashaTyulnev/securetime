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

    public function findShiftsForToday(): array
    {
        $allEmployees = $this->employeeService->getAllEmployeeEntities(true);
        $tz = new \DateTimeZone(self::BUSINESS_TIMEZONE);

        return $this->shiftRepository->findShiftsForToday($allEmployees, $tz);
    }

    /** Zeitzone für Schichtlogik (Check-in-Fenster etc.) – gleiche Zone wie Nutzer. */
    private const BUSINESS_TIMEZONE = 'Europe/Berlin';

    /**
     * Prüft die heutige Schicht des Benutzers. Bei mehreren Schichten heute wird die Schicht
     * berücksichtigt, die noch nicht beendet ist (workEnd > now) – damit kann sich der User
     * in eine spätere Schicht am selben Tag einchecken.
     *
     * @param \DateTimeInterface|null $now Optional: aktueller Zeitpunkt (für Tests). Sonst „jetzt“ in Europe/Berlin.
     */
    public function checkUserPlanForToday(?User $user, ?\DateTimeInterface $now = null): array
    {
        $todayShifts = $this->findShiftsForToday();
        
        if (!$user) {
            return [
                'minutesDifferenceToWorkBegin' => null,
                'workingToday' => false
            ];
        }

        $tz = new \DateTimeZone(self::BUSINESS_TIMEZONE);
        $nowDateTime = $now !== null
            ? (($now instanceof DateTime) ? $now : DateTime::createFromInterface($now))
            : new DateTime('now', $tz);
        if ($nowDateTime->getTimezone()->getName() !== $tz->getName()) {
            $nowDateTime = $nowDateTime->setTimezone($tz);
        }

        $userShiftsToday = [];
        foreach ($todayShifts as $shift) {
            $employee = $shift->getEmployee();
            if ($employee && $employee->getUser() && $employee->getUser()->getId() === $user->getId()) {
                $userShiftsToday[] = $shift;
            }
        }

        if ($userShiftsToday === []) {
            return [
                'minutesDifferenceToWorkBegin' => null,
                'workingToday' => false
            ];
        }

        // Nach Schichtbeginn sortieren, dann erste Schicht wählen, die noch nicht beendet ist (workEnd > now)
        usort($userShiftsToday, static function ($a, $b) use ($tz): int {
            $dateA = $a->getDate();
            $timeA = $a->getDemandShift()?->getTimeFrom();
            $dateB = $b->getDate();
            $timeB = $b->getDemandShift()?->getTimeFrom();
            if ($dateA === null || $timeA === null) {
                return 1;
            }
            if ($dateB === null || $timeB === null) {
                return -1;
            }
            $beginA = new DateTime($dateA->format('Y-m-d') . ' ' . $timeA->format('H:i:s'), $tz);
            $beginB = new DateTime($dateB->format('Y-m-d') . ' ' . $timeB->format('H:i:s'), $tz);
            return $beginA <=> $beginB;
        });

        foreach ($userShiftsToday as $shift) {
            $demandShift = $shift->getDemandShift();
            $workEndTime = $demandShift?->getTimeTo();
            $workBeginTime = $demandShift?->getTimeFrom();
            $shiftDate = $shift->getDate();

            if ($workEndTime === null || $workBeginTime === null || $shiftDate === null) {
                continue;
            }

            $workEndDateTime = new DateTime(
                $shiftDate->format('Y-m-d') . ' ' . $workEndTime->format('H:i:s'),
                $tz
            );

            if ($workEndDateTime < $nowDateTime) {
                continue;
            }

            $workBeginDateTime = new DateTime(
                $shiftDate->format('Y-m-d') . ' ' . $workBeginTime->format('H:i:s'),
                $tz
            );

            $minutesUntilBegin = (int) (($workBeginDateTime->getTimestamp() - $nowDateTime->getTimestamp()) / 60);
            $minutesDifferenceToWorkBegin = -$minutesUntilBegin;

            return [
                'minutesDifferenceToWorkBegin' => $minutesDifferenceToWorkBegin,
                'workingToday' => true
            ];
        }

        return [
            'minutesDifferenceToWorkBegin' => null,
            'workingToday' => true
        ];
    }

    /**
     * Liefert das geplante Schichtende (Datum + Uhrzeit) der heutigen Schicht des Benutzers.
     * Nur sinnvoll, wenn der Benutzer heute eine Schicht hat (workingToday).
     *
     * @return \DateTimeInterface|null Geplantes Ende heute in BUSINESS_TIMEZONE, oder null
     */
    public function getTodayShiftPlannedEnd(?User $user): ?\DateTimeInterface
    {
        if (!$user) {
            return null;
        }

        $todayShifts = $this->findShiftsForToday();
        $tz = new \DateTimeZone(self::BUSINESS_TIMEZONE);

        foreach ($todayShifts as $shift) {
            $employee = $shift->getEmployee();
            if ($employee && $employee->getUser() && $employee->getUser()->getId() === $user->getId()) {
                $demandShift = $shift->getDemandShift();
                $workEndTime = $demandShift?->getTimeTo();
                $shiftDate = $shift->getDate();

                if ($workEndTime === null || $shiftDate === null) {
                    return null;
                }

                return new DateTime(
                    $shiftDate->format('Y-m-d') . ' ' . $workEndTime->format('H:i:s'),
                    $tz
                );
            }
        }

        return null;
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
