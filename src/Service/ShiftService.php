<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\DemandShiftRepository;
use App\Repository\ShiftPresetRepository;
use App\Repository\ShiftRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShiftService
{
    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        public ShiftPresetRepository $presetRepository,
        public DemandShiftRepository $demandShiftRepository,
        public SerializerInterface $serializer,
        public ShiftRepository $shiftRepository,
        public Security $security,
        public EmployeeService $employeeService,
        public HttpClientInterface $httpClient
    ) {}

    /* ---------------------------------------------
     * BASIC HELPERS
     * --------------------------------------------- */

    private function tz(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    private function now(?DateTimeInterface $now = null): DateTime
    {
        return $now
            ? DateTime::createFromInterface($now)->setTimezone($this->tz())
            : new DateTime('now', $this->tz());
    }

    private function buildDateTime(DateTimeInterface $date, DateTimeInterface $time): DateTime
    {
        return new DateTime(
            $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
            $this->tz()
        );
    }

    /* ---------------------------------------------
     * SIMPLE API METHODS
     * --------------------------------------------- */

    public function getAllDemandShiftPresets(): array
    {
        return $this->json(
            $this->presetRepository->findAll(),
            'shiftPreset:read'
        );
    }

    public function getDemandShiftsOfFacilityInDateRange($facilityId, $from, $to): array
    {
        $partnerCompany = $this->security->getUser()
            ?->getEmployee()
            ?->getPartnerCompany();

        $shifts = $this->demandShiftRepository
            ->findDemandShiftsOfFacilityInDateRange($facilityId, $from, $to, $partnerCompany);

        return $this->json($shifts, 'demandShift:read');
    }

    private function json($data, string $group): array
    {
        return json_decode(
            $this->serializer->serialize($data, 'jsonld', [
                'groups' => [$group]
            ]),
            true
        );
    }

    /* ---------------------------------------------
     * SHIFT LOGIC
     * --------------------------------------------- */

    public function findShiftsForToday(): array
    {
        $employees = $this->employeeService->getAllEmployeeEntities(true);

        return $this->shiftRepository
            ->findShiftsForToday($employees, $this->tz());
    }

    private function getUserShiftsToday(User $user): array
    {
        return array_filter(
            $this->findShiftsForToday(),
            fn($shift) =>
                $shift->getEmployee()?->getUser()?->getId() === $user->getId()
        );
    }

    /**
     * Check: arbeitet der User heute?
     * Und wie viele Minuten seit Schichtbeginn?
     */
    public function checkUserPlanForToday(?User $user, ?DateTimeInterface $now = null): array
    {
        if (!$user) {
            return ['workingToday' => false, 'minutesDifferenceToWorkBegin' => null];
        }

        $now = $this->now($now);

        // ✅ 1) aktive Schicht suchen (läuft gerade)
        $activeShift = $this->findActiveShift($user, $now);

        if (!$activeShift) {
            return [
                'workingToday' => false,
                'minutesDifferenceToWorkBegin' => null
            ];
        }

        // ✅ 2) Minuten seit Beginn berechnen
        $start = $activeShift->shiftBeginn;

        $minutes = (int)(($now->getTimestamp() - $start->getTimestamp()) / 60);

        return [
            'workingToday' => true,
            'minutesDifferenceToWorkBegin' => $minutes
        ];
    }


    /**
     * Geplantes Ende der heutigen Schicht
     */
    public function getTodayShiftPlannedEnd(?User $user): ?DateTimeInterface
    {
        if (!$user) {
            return null;
        }

        $shift = $this->getUserShiftsToday($user)[0] ?? null;

        if (!$shift) {
            return null;
        }

        $date = $shift->getDate();
        $to = $shift->getDemandShift()?->getTimeTo();

        return ($date && $to)
            ? $this->buildDateTime($date, $to)
            : null;
    }

    /**
     * Nächste geplante Schicht (max 30 Tage)
     */
    public function findNextPlannedShift(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $now = $this->now();

        $upcoming = $this->findUpcomingShiftsForUser($user, 30);

        foreach ($upcoming as $shift) {

            $start = $shift->shiftBeginn ?? null;

            if ($start && $start > $now) {

                return [
                    'shift' => $shift,
                    'facility' => $shift->getDemandShift()?->getFacility(),
                    'name' => $shift->getDemandShift()?->getName(),
                    'timeFrom' => $shift->getDemandShift()?->getTimeFrom(),
                    'timeTo' => $shift->getDemandShift()?->getTimeTo(),
                ];
            }
        }

        return null;
    }


    /**
     * Kommende Schichten in den nächsten X Tagen
     */
    public function findUpcomingShiftsForUser(?User $user, int $days = 7): array
    {
        if (!$user?->getEmployee()) {
            return [];
        }

        $today = (new \DateTimeImmutable('now', $this->tz()))->setTime(0, 0, 0);
        $end = $today->modify("+{$days} days");

        return $this->shiftRepository->getAllAssignedShiftsInRange(
            $user->getEmployee(),
            $today->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }



    public function getDashboardWorkingInfo(
        ?User $user,
        \App\Service\TimeTracker\TimeTrackerService $timeTrackerService
    ): array
    {
        if (!$user || !$user->getEmployee()) {
            return $this->emptyDashboardStatus();
        }

        $now = $this->now();

        // -------------------------------------------------
        // 1) TimeTracker Status (immer höchste Priorität)
        // -------------------------------------------------
        $isCheckedIn = $timeTrackerService->checkIfUserIsCheckedIn($user);
        $hasPause    = $timeTrackerService->checkIfUserHasPause($user);
        $isCheckedOut = $timeTrackerService->checkIfUserIsCheckedOut($user);

        // Wenn eingecheckt oder Pause → Tracker IMMER anzeigen
        if ($isCheckedIn || $hasPause) {
            return [
                'state' => 'CHECKED_IN',
                'workingToday' => true,

                'isCheckedIn' => $isCheckedIn,
                'hasPause' => $hasPause,
                'isCheckedOut' => $isCheckedOut,

                'minutesDifferenceToWorkBegin' => null,
                'nextPlannedShift' => null,
                'earlyCheckoutToday' => false,
            ];
        }

        // -------------------------------------------------
        // 2) Active Shift läuft gerade (Night Shift Safe)
        //    → Wenn nicht eingecheckt und >25 Min nach Start: LATE
        // -------------------------------------------------
        $activeShift = $this->findActiveShift($user, $now);

        if ($activeShift) {
            $minutesSinceStart =
                (int)(($now->getTimestamp() - $activeShift->shiftBeginn->getTimestamp()) / 60);

            // Benutzer arbeitet laut Plan gerade, ist aber nicht eingecheckt:
            // ab 25 Minuten nach Schichtbeginn als „zu spät“ markieren
            if (!$isCheckedIn && !$hasPause && $minutesSinceStart > 25) {
                return [
                    'state' => 'LATE',
                    'workingToday' => true,

                    'isCheckedIn' => false,
                    'hasPause' => false,
                    'isCheckedOut' => $isCheckedOut,

                    'minutesDifferenceToWorkBegin' => $minutesSinceStart,

                    'nextPlannedShift' => null,
                    'earlyCheckoutToday' => false,
                ];
            }

            // Fallback: Active-Status (wird aktuell faktisch nicht genutzt,
            // da eingecheckte Nutzer oben bereits als CHECKED_IN behandelt werden)
            return [
                'state' => 'ACTIVE',
                'workingToday' => true,

                'isCheckedIn' => $isCheckedIn,
                'hasPause' => $hasPause,
                'isCheckedOut' => $isCheckedOut,

                'minutesDifferenceToWorkBegin' => $minutesSinceStart,

                'activeShiftBeginn' => $activeShift->shiftBeginn,
                'activeShiftEnd' => $activeShift->shiftEnd,

                'nextPlannedShift' => null,
                'earlyCheckoutToday' => false,
            ];
        }

        // -------------------------------------------------
        // 3) Next Shift finden
        // -------------------------------------------------
        $nextShift = $this->findNextShiftEntity($user, $now);

        if ($nextShift) {

            $minutesUntilStart =
                (int)(($nextShift->shiftBeginn->getTimestamp() - $now->getTimestamp()) / 60);

            // Zu spät wenn Shift schon >25 Minuten gestartet wäre
            if ($minutesUntilStart < -25) {
                return [
                    'state' => 'LATE',
                    'workingToday' => true,

                    'isCheckedIn' => false,
                    'hasPause' => false,
                    'isCheckedOut' => true,

                    'minutesDifferenceToWorkBegin' => abs($minutesUntilStart),

                    'nextPlannedShift' => null,
                    'earlyCheckoutToday' => false,
                ];
            }

            // UPCOMING Shift (heute oder nächste Tage)
            return [
                'state' => 'UPCOMING',
                'workingToday' => true,

                'isCheckedIn' => false,
                'hasPause' => false,
                'isCheckedOut' => true,

                // negativ = beginnt bald
                'minutesDifferenceToWorkBegin' => $minutesUntilStart,

                'nextPlannedShift' => [
                    'shift' => $nextShift,
                    'facility' => $nextShift->getDemandShift()?->getFacility(),
                    'name' => $nextShift->getDemandShift()?->getName(),
                    'timeFrom' => $nextShift->getDemandShift()?->getTimeFrom(),
                    'timeTo' => $nextShift->getDemandShift()?->getTimeTo(),
                    'dateTime' => $nextShift->shiftBeginn,
                ],

                'earlyCheckoutToday' => false,
            ];
        }

        // -------------------------------------------------
        // 4) OFF (keine Schicht)
        // -------------------------------------------------
        return $this->emptyDashboardStatus();
    }

    private function emptyDashboardStatus(): array
    {
        return [
            'state' => 'OFF',
            'workingToday' => false,

            'isCheckedIn' => false,
            'hasPause' => false,
            'isCheckedOut' => true,

            'minutesDifferenceToWorkBegin' => null,
            'nextPlannedShift' => null,
            'earlyCheckoutToday' => false,
        ];
    }

    private function findActiveShift(User $user, DateTime $now): ?object
    {
        $shifts = $this->findUpcomingShiftsForUser($user, 7);

        foreach ($shifts as $shift) {

            $start = $shift->shiftBeginn ?? null;
            $end   = $shift->shiftEnd ?? null;

            if (!$start || !$end) {
                continue;
            }

            if ($now >= $start && $now <= $end) {
                return $shift;
            }
        }

        return null;
    }


    private function findNextShiftEntity(User $user, DateTime $now): ?object
    {
        $shifts = $this->findUpcomingShiftsForUser($user, 30);

        foreach ($shifts as $shift) {

            $start = $shift->shiftBeginn ?? null;

            if ($start && $start > $now) {
                return $shift;
            }
        }

        return null;
    }

}
