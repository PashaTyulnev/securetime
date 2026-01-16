<?php

namespace App\Service\TimeTracker;

use App\Entity\TimeTracker;
use App\Entity\User;

use App\Repository\TimeTrackerRepository;
use Carbon\Carbon;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class TimeTrackerService
{

    public function __construct(public EntityManagerInterface $em,
                                public TimeTrackerRepository  $timeTrackerRepository,
                                public Security               $security,
                                public Environment            $twig
    )
    {

    }

    /**
     * @throws \Exception
     */
    public function checkin(?UserInterface $user): void
    {
        $userIsCheckedIn = $this->checkIfUserIsCheckedIn($user);
        $userHasPause = $this->checkIfUserHasPause($user);

        if (!$userIsCheckedIn && !$userHasPause) {
            $this->createTimeTrackerEntry($user, "checkin", true, null);
        } elseif (!$userIsCheckedIn && $userHasPause) {
            $lastMainCheckin = $this->getLastMainCheckin($user);
            $this->createTimeTrackerEntry($user, "checkin", false, $lastMainCheckin);

        } else {
            throw new \Exception('Benutzer ist schon eingecheckt');
        }

    }

    private function createTimeTrackerEntry(?UserInterface $user, $type, $isShiftStart, $parent): void
    {
        $timeTracker = new TimeTracker();
        $timeTracker->setUser($user);
        $timeTracker->setCreatedAt(new \DateTimeImmutable());
        $timeTracker->setTimestamp(new \DateTimeImmutable());
        $timeTracker->setType($type);

        if ($type === "checkin" and $isShiftStart) {
            $timeTracker->setShiftStart(true);
        } else {
            $timeTracker->setShiftStart(false);
        }

        $timeTracker->setParent($parent);

        $this->em->persist($timeTracker);
        $this->em->flush();
    }

    public function checkout(?UserInterface $user): void
    {
        $userIsCheckedOut = $this->checkIfUserIsCheckedOut($user);

        if (!$userIsCheckedOut) {
            $lastMainCheckin = $this->getLastMainCheckin($user);
            $this->createTimeTrackerEntry($user, "checkout", false, $lastMainCheckin);
        } else {
            throw new \Exception('Benutzer ist schon ausgecheckt');
        }
    }


    public function checkIfUserIsCheckedIn(?UserInterface $user): bool
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);

        if ($lastTimeTracker && $lastTimeTracker->getType() == 'checkin') {
            return true;
        }

        return false;
    }

    public function getLastMainCheckin(?UserInterface $user): ?TimeTracker
    {
        return $this->timeTrackerRepository->findOneBy(['user' => $user, 'type' => 'checkin', 'isShiftStart' => true], ['id' => 'DESC']);
    }

    public function checkIfUserIsCheckedOut(?UserInterface $user): bool
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);

        if ($lastTimeTracker && $lastTimeTracker->getType() == 'checkout') {
            return true;
        }
        return false;
    }

    public function checkIfUserHasPause(?UserInterface $user): bool
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);

        if ($lastTimeTracker && $lastTimeTracker->getType() == 'pause') {
            return true;
        }
        return false;
    }

    public function pause(?UserInterface $getUser)
    {
        $userIsCheckedIn = $this->checkIfUserIsCheckedIn($getUser);
        $userHasPause = $this->checkIfUserHasPause($getUser);

        if ($userIsCheckedIn && !$userHasPause) {
            $lastMainCheckin = $this->getLastMainCheckin($getUser);
            $this->createTimeTrackerEntry($getUser, "pause", false, $lastMainCheckin);
        } else {
            throw new \Exception('Benutzer ist nicht eingecheckt oder hat schon eine Pause');
        }
    }

    public function getLastTimeStamp(?UserInterface $getUser): ?array
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $getUser], ['id' => 'DESC']);

        $lastMainCheckin = $this->getLastMainCheckin($getUser);

        if ($lastTimeTracker) {
            return [
                'type' => $lastTimeTracker->getType(),
                'timestamp' => $lastTimeTracker->getTimestamp(),
                'mainCheckin' => $lastMainCheckin?->getTimestamp()
            ];
        }

        return null;
    }

    public function getAllWorkingTimes($year, $month, $userId): array
    {
        $currentUser = $this->security->getUser();
        $userRoles = $currentUser->getRoles();

        if (!in_array('ROLE_ADMIN', $userRoles)) {
            $userId = $currentUser->getId();
        }

        $allTimeTrackEntities = $this->fetchTimeTrackEntities($userId);
        $output = $this->processTimeTrackEntities($allTimeTrackEntities);
        return $this->filterByMonthAndYear($output, $year, $month);
    }

    private function fetchTimeTrackEntities($userId): array
    {
        $criteria = $userId ? ['user' => $userId] : [];
        return $this->timeTrackerRepository->findBy($criteria, ['id' => 'ASC']);
    }

    private function processTimeTrackEntities(array $entities): array
    {
        $output = [];

        foreach ($entities as $entity) {
            $this->processEntity($entity, $output);
        }


        $formatedOutput = $this->formatOutput($output);

        $formatedOutput = $this->calculateTotalPauseOfSingleShift($formatedOutput);
        $formatedOutput = $this->calculateTotalWorkingTime($formatedOutput);
        return $formatedOutput;
    }

    private function processEntity($entity, array &$output): void
    {
        $user = $entity->getUser();
        $id = $entity->getId();
        $type = $entity->getType();
        $timestamp = $entity->getTimestamp();
        $parent = $entity->getParent()?->getId();


        if ($entity->isShiftStart()) {
            $this->handleShiftStart($output, $id, $user->getEmployee()->getFullName(), $timestamp);
        } elseif (isset($output[$parent]) && $type === 'checkout') {
            $this->handleShiftEnd($output[$parent], $timestamp);
        } elseif (isset($output[$parent]) && $type === 'pause') {
            $this->handlePause($output[$parent], $timestamp);
        } elseif (isset($output[$parent]) && $type === 'checkin') {
            $this->handleCheckIn($output[$parent], $timestamp);
        }



    }

    private function handleShiftStart(array &$output, int $id, string $username, $timestamp): void
    {
        $output[$id] = [
            'timeTrackerId' => $id,
            'username' => $username,
            'shiftStart' => $timestamp,
            'shiftEnd' => null,
            'shiftDuration' => null,
            'pauseDuration' => new \DateInterval('PT0S'),
            'pausePeriods' => [],
        ];
    }

    private function handleShiftEnd(array &$entry, $timestamp): void
    {
        $entry['shiftEnd'] = $timestamp;
        $entry['shiftDuration'] = $entry['shiftStart']->diff($timestamp);
    }

    private function handleCheckIn(array &$entry, $timestamp): void
    {
        $entry['pausePeriods'][] = ['start' => null, 'end' => $timestamp];
    }

    private function handlePause(array &$entry, $timestamp): void
    {
        $lastPause = end($entry['pausePeriods']);

        if (!$lastPause || isset($lastPause['end'])) {
            $entry['pausePeriods'][] = ['start' => $timestamp, 'end' => null];
        } else {
            $lastIndex = array_key_last($entry['pausePeriods']);
            $entry['pausePeriods'][$lastIndex]['end'] = $timestamp;

            $pauseStart = $entry['pausePeriods'][$lastIndex]['start'];
            $pauseEnd = $entry['pausePeriods'][$lastIndex]['end'];
            $pauseDuration = $pauseStart->diff($pauseEnd);
            $entry['pauseDuration'] = $this->addIntervals($entry['pauseDuration'], $pauseDuration);
        }
    }

    private function formatOutput(array $output): array
    {
        return array_map(function ($entry) {
            $entry['shiftDuration'] = $entry['shiftDuration'] ? $entry['shiftDuration']->format('%H:%I') : null;
            $entry['pauseDuration'] = $entry['pauseDuration'] ? $entry['pauseDuration']->format('%H:%I') : null;
            $entry['totalWorkingTime'] = $entry['shiftDuration'];

            return $entry;
        }, array_values($output));
    }


    /**
     * Addiert zwei DateInterval-Objekte.
     */
    private function addIntervals(\DateInterval $a, \DateInterval $b): \DateInterval
    {
        $start = new \DateTimeImmutable();
        $end = $start->add($a)->add($b);

        return $start->diff($end);
    }

    public function getAllAvailableMonths(): array
    {
        $allTimeTrackEntities = $this->timeTrackerRepository->findBy([], ['id' => 'ASC']);

        $output = [];

        foreach ($allTimeTrackEntities as $timeTrackEntity) {
            $timestamp = $timeTrackEntity->getTimestamp();
            $month = $timestamp->format('m');
            $year = $timestamp->format('Y');

            $output[$year][$month] = $month;
        }

        return $output;

    }

    private function filterByMonthAndYear(array $output, $year, $month): array
    {
        foreach ($output as $key => $entry) {
            $timestamp = $entry['shiftStart'];
            $entryMonth = $timestamp->format('m');
            $entryYear = $timestamp->format('Y');

            if ($entryMonth != $month || $entryYear != $year) {
                unset($output[$key]);
            }
        }

        //array_values() um die Indizes neu zu sortieren
        return array_values($output);
    }

    public function getLastUserWorkingTimes(): array
    {
        //Für alle Users den letzten TimeTracker Eintrag holen
        $lastUserWorkingTimes = $this->timeTrackerRepository->lastUserWorkingTimes();

        return $this->formatLastUserWorkingTimes($lastUserWorkingTimes);

    }

    private function formatLastUserWorkingTimes($lastUserWorkingTimes): array
    {
        $output = [];

        foreach ($lastUserWorkingTimes as $timeTracker) {

            $user = $timeTracker->getUser();

            if ($user->isActive() === false) {
                continue;
            }

            $username = $user->getUsername();
            $timestamp = $timeTracker->getTimestamp();
            $type = $timeTracker->getType();
            $id = $timeTracker->getId();
            $parent = $timeTracker->getParent()?->getId();

            $output[] = [
                'timeTrackerId' => $id,
                'username' => $username,
                'lastActionTimeStamp' => $timestamp,
                'type' => $type,
                'parent' => $parent,
                'userId' => $user->getId()
            ];
        }

        return $output;
    }

    public function calculateUserTotals(mixed $userTimeTrackingData): array
    {

        if ($this->hasDifferentUsers($userTimeTrackingData)) {
            $userName = "Alle User";
        } else {
            $userName = $userTimeTrackingData[0]->username;
        }

        $userTimeTrackingData = $this->calculateNightShiftWorkingTime($userTimeTrackingData);

        $summaryData = $this->calculateTotalsOfTimeSummary($userTimeTrackingData);

        $totalWorkingMinutes = $summaryData['totalWorkingMinutes'];
        $totalPauseMinutes = $summaryData['totalPauseMinutes'];
        $totalShiftDuration = $summaryData['totalShiftDuration'];

        $totalNightShiftPause = $summaryData['totalNightShiftPause'];
        $totalNightShiftWorkingTime = $summaryData['totalNightShiftWorkingTime'];
        $totalNightShiftWithoutPause = $summaryData['totalNightShiftWithoutPause'];

        $dateRange = $this->getDateRange($userTimeTrackingData);


        return [
            'totalWorkingTime' => $totalWorkingMinutes,
            'dateFrom' => $dateRange['minDate'],
            'dateTo' => $dateRange['maxDate'],
            'totalPause' => $totalPauseMinutes,
            'totalShiftDuration' => $totalShiftDuration,
            'username' => $userName,
            'totalNightShiftPause' => $totalNightShiftPause,
            'totalNightShiftWorkingTime' => $totalNightShiftWorkingTime,
            'totalNightShiftWithoutPause' => $totalNightShiftWithoutPause,

        ];
    }

    public function hasDifferentUsers($userTimeTrackingData): bool
    {
        if (empty($userTimeTrackingData)) {
            return false; // Kein Benutzer vorhanden
        }

        $firstUser = $userTimeTrackingData[0]->username;

        foreach ($userTimeTrackingData as $entry) {
            if ($entry->username !== $firstUser) {
                return true; // Es gibt mindestens einen anderen Benutzer
            }
        }

        return false; // Alle Benutzer sind gleich
    }


    /**
     * Berechnet die gesamte Arbeitszeit in Minuten
     */
    private function calculateTotalsOfTimeSummary(array $userTimeTrackingData): array
    {

        $totalWorkingMinutes = 0;
        $totalPauseMinutes = 0;
        $totalShiftDuration = 0;
        $totalNightShiftPause = 0;
        $totalNightShiftWorkingTime = 0;
        $totalNightShiftWithoutPause = 0;

        foreach ($userTimeTrackingData as $timeEntry) {
            $workingTime = $timeEntry->totalWorkingTime; // Format "hh:mm"
            $pauseTime = $timeEntry->pauseDuration; // Format "hh:mm"
            $shiftTime = $timeEntry->shiftDuration; // Format "hh:mm"

            if ($workingTime !== null) {
                list($hours, $minutes) = explode(":", $workingTime);
                $totalWorkingMinutes += ((int)$hours * 60) + (int)$minutes;
            }

            if ($pauseTime !== null) {
                list($hours, $minutes) = explode(":", $pauseTime);
                $totalPauseMinutes += ((int)$hours * 60) + (int)$minutes;
            }

            if ($shiftTime !== null) {
                list($hours, $minutes) = explode(":", $shiftTime);
                $totalShiftDuration += ((int)$hours * 60) + (int)$minutes;
            }

            if (isset($timeEntry->nightShiftPause)) {
                list($hours, $minutes) = explode(":", $timeEntry->nightShiftPause);
                $totalNightShiftPause += ((int)$hours * 60) + (int)$minutes;
            }

            if (isset($timeEntry->nightShiftTotal)) {

                list($hours, $minutes) = explode(":", $timeEntry->nightShiftTotal);
                $totalNightShiftWorkingTime += ((int)$hours * 60) + (int)$minutes;

            }

            if (isset($timeEntry->nightShiftWithoutPause)) {
                list($hours, $minutes) = explode(":", $timeEntry->nightShiftWithoutPause);
                $totalNightShiftWithoutPause += ((int)$hours * 60) + (int)$minutes;
            }
        }

        return [
            'totalWorkingMinutes' => $this->formatMinutesToTime($totalWorkingMinutes),
            'totalPauseMinutes' => $this->formatMinutesToTime($totalPauseMinutes),
            'totalShiftDuration' => $this->formatMinutesToTime($totalShiftDuration),
            'totalNightShiftPause' => $this->formatMinutesToTime($totalNightShiftPause),
            'totalNightShiftWorkingTime' => $this->formatMinutesToTime($totalNightShiftWorkingTime),
            'totalNightShiftWithoutPause' => $this->formatMinutesToTime($totalNightShiftWithoutPause),
        ];

    }


    /**
     * Ermittelt das kleinste und größte Startdatum aus den Schichtdaten
     */
    private function getDateRange(array $userTimeTrackingData): array
    {
        $minDate = null;
        $maxDate = null;

        foreach ($userTimeTrackingData as $timeEntry) {
            $shiftStart = $timeEntry->shiftStart->date;

            if ($minDate === null || $shiftStart < $minDate) {
                $minDate = $shiftStart;
            }
            if ($maxDate === null || $shiftStart > $maxDate) {
                $maxDate = $shiftStart;
            }
        }

        return [
            'minDate' => $minDate,
            'maxDate' => $maxDate,
        ];
    }

    /**
     * Formatiert Minuten in das "hh:mm"-Format
     */
    private function formatMinutesToTime(int $totalMinutes): string
    {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf("%02d:%02d", $hours, $minutes);
    }

    private function calculateTotalPauseOfSingleShift(array $formatedOutput): array
    {
        foreach ($formatedOutput as $key => $entry) {

            $pausePeriods = $entry['pausePeriods'];

            $pauseStart = null;
            $pauseEnd = null;

            $totalPauseDuration = new \DateInterval('PT0S');


            foreach ($pausePeriods as $pausePeriod) {

                if ($pausePeriod['start'] !== null) {
                    $pauseStart = $pausePeriod['start'];
                } elseif ($pausePeriod['end'] !== null) {
                    $pauseEnd = $pausePeriod['end'];
                    $pauseDuration = $pauseStart->diff($pauseEnd);
                    $totalPauseDuration = $this->addIntervals($totalPauseDuration, $pauseDuration);
                }
            }

            $formatedOutput[$key]['pauseDuration'] = $totalPauseDuration->format('%H:%I');
        }


        return $formatedOutput;
    }

    private function calculateTotalWorkingTime(array $formatedOutput)
    {
        foreach ($formatedOutput as $key => $entry) {

            if ($entry['pauseDuration'] === null || $entry['shiftDuration'] === null) {
                // Speichere das Ergebnis formatiert als HH:MM
                $formatedOutput[$key]['totalWorkingTime'] = $entry['shiftDuration'];
                continue;
            }

            $shiftDuration = $this->convertToDateInterval($entry['shiftDuration']);
            $pauseDuration = $this->convertToDateInterval($entry['pauseDuration']);

            // Berechne die effektive Arbeitszeit
            $countedShiftDuration = $this->subtractIntervals($shiftDuration, $pauseDuration);

            // Speichere das Ergebnis formatiert als HH:MM
            $formatedOutput[$key]['totalWorkingTime'] = $countedShiftDuration->format('%H:%I');
        }

        return $formatedOutput;
    }

// Wandelt "HH:MM" in ein DateInterval um
    private function convertToDateInterval(string $timeString): \DateInterval
    {
        list($hours, $minutes) = explode(':', $timeString);
        return new \DateInterval(sprintf('PT%sH%sM', (int)$hours, (int)$minutes));
    }

// Subtrahiert zwei DateInterval-Objekte
    private function subtractIntervals(\DateInterval $shiftDuration, \DateInterval $pauseDuration): \DateInterval
    {
        $start = new \DateTimeImmutable('00:00:00');
        $end = $start->add($shiftDuration)->sub($pauseDuration);

        return $start->diff($end);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function saveChangedTimeEntry(mixed $requestContent): void
    {
        $shiftStart = new \DateTimeImmutable($requestContent->shiftStartDate . ' ' . $requestContent->shiftStartTime);
        $shiftEnd = new \DateTimeImmutable($requestContent->shiftEndDate . ' ' . $requestContent->shiftEndTime);

        //Parent TimeTracker ID
        $timeTrackerId = $requestContent->timeTrackerId;

        $timeTrackerEntryCheckin = $this->timeTrackerRepository->findOneBy(['id' => $timeTrackerId]);
        $timeTrackerEntryCheckout = $this->timeTrackerRepository->findOneBy(['parent' => $timeTrackerId, 'type' => 'checkout']);

        $user = $timeTrackerEntryCheckin->getUser();
        $this->validateInputDates($user, $timeTrackerId, $shiftStart, $shiftEnd, $timeTrackerEntryCheckin, $timeTrackerEntryCheckout);

        $this->em->persist($timeTrackerEntryCheckin);
        $this->em->persist($timeTrackerEntryCheckout);

        $this->em->flush();

    }

    /**
     * @param \App\Entity\User|null $user
     * @param mixed $timeTrackerId
     * @param \DateTimeImmutable $shiftStart
     * @param \DateTimeImmutable $shiftEnd
     * @param object|TimeTracker|null $timeTrackerEntryCheckin
     * @param object|TimeTracker|null $timeTrackerEntryCheckout
     * @return void
     * @throws \DateMalformedStringException
     */
    public function validateInputDates(User $user, mixed $timeTrackerId, \DateTimeImmutable $shiftStart, \DateTimeImmutable $shiftEnd, $timeTrackerEntryCheckin, $timeTrackerEntryCheckout): void
    {

        //Vorheriger und nächster Main Checkin Eintrag
        $previousTimeTrackerMainCheckinEntry = $this->timeTrackerRepository->findPreviousMainCheckinEntry($user, $timeTrackerId);
        $previousTimeTrackerMainCheckoutEntry = $this->timeTrackerRepository->findOneBy(['parent' => $previousTimeTrackerMainCheckinEntry->getId(), 'type' => 'checkout']);


        if ($previousTimeTrackerMainCheckoutEntry !== null) {
            $previousCheckoutDateTime = $previousTimeTrackerMainCheckoutEntry->getTimestamp();
            if ($shiftStart < $previousCheckoutDateTime) {
                throw new \DateMalformedStringException('Schichtanfang darf nicht vor dem letzten Schichtende liegen');
            }
        }


        $nextTimeTrackerMainCheckinEntry = $this->timeTrackerRepository->findNextMainCheckinEntry($user, $timeTrackerId);

        if ($nextTimeTrackerMainCheckinEntry !== null) {
            $nextTimeTrackerMainCheckinEntryDate = $nextTimeTrackerMainCheckinEntry->getTimestamp();

            if ($shiftEnd > $nextTimeTrackerMainCheckinEntryDate) {
                throw new \DateMalformedStringException('Schichtende darf nicht nach dem nächsten Schichtanfang liegen');
            }
        }

        $timeTrackerEntryCheckin->setTimestamp($shiftStart);
        $timeTrackerEntryCheckout->setTimestamp($shiftEnd);

        //shift end darf nicht vor shift start sein
        if ($shiftEnd < $shiftStart) {
            throw new \DateMalformedStringException('Ende der Schicht darf nicht vor dem Start sein');
        }
    }

    /**
     * Zählt wie viele Minuten in der Nacht gearbeitet wurden ( 00:00 - 06:00)
     */
    private function calculateNightShiftWorkingTime(mixed $userTimeTrackingData, $nightShiftBeginHour = 0, $nightShiftEndHour = 6): array
    {
        foreach ($userTimeTrackingData as $timeEntry) {
            if ($timeEntry->shiftStart === null || $timeEntry->shiftEnd === null) {
                continue;
            }

            $shiftStart = $timeEntry->shiftStart->date;
            $shiftStartCarbon = new Carbon($shiftStart);
            $shiftEnd = $timeEntry->shiftEnd->date;
            $shiftEndCarbon = new Carbon($shiftEnd);

            $pausePeriods = $timeEntry->pausePeriods;

            $nightShiftPauseDuration = 0;

            foreach ($pausePeriods as $pausePeriod) {

                $pauseStart = $pausePeriod->start;
                $pauseEnd = $pausePeriod->end;

                if ($pauseStart !== null) {
                    $pauseStart = $pauseStart->date;
                    $pauseStartCarbon = new Carbon($pauseStart);

                } elseif ($pauseEnd !== null) {
                    $pauseEnd = $pauseEnd->date;
                    $pauseEndCarbon = new Carbon($pauseEnd);

                    if (isset($pauseStartCarbon)) {
                        $nightShiftPauseDuration += $this->getNightShiftMinutesBetweenTimestamps($pauseStartCarbon, $pauseEndCarbon, $nightShiftBeginHour, $nightShiftEndHour);
                    }
                }
            }


            $nightShiftTotalMinutes = $this->getNightShiftMinutesBetweenTimestamps($shiftStartCarbon, $shiftEndCarbon, $nightShiftBeginHour, $nightShiftEndHour);


            $nightShiftTotalString = $this->formatMinutesToTime($nightShiftTotalMinutes);
            $nightShiftPauseString = $this->formatMinutesToTime($nightShiftPauseDuration);
            $totalNightShiftWithoutPause = $nightShiftTotalMinutes - $nightShiftPauseDuration;
            $totalNightShiftWithoutPauseString = $this->formatMinutesToTime($totalNightShiftWithoutPause);

            $timeEntry->nightShiftTotal = $nightShiftTotalString;
            $timeEntry->nightShiftPause = $nightShiftPauseString;
            $timeEntry->nightShiftWithoutPause = $totalNightShiftWithoutPauseString;


        }

        return $userTimeTrackingData;
    }


    public function getNightShiftMinutesBetweenTimestamps(Carbon $fromCarbon, Carbon $toCarbon, mixed $nightShiftBeginHour, mixed $nightShiftEndHour): int
    {
        $nightShiftMinutes = 0;

        $shiftStartTimeStamp = $fromCarbon->timestamp;
        $shiftEndTimeStamp = $toCarbon->timestamp;

        //iterate over all minutes of the shift
        for ($currentTimeStamp = $shiftStartTimeStamp; $currentTimeStamp <= $shiftEndTimeStamp; $currentTimeStamp += 60) {
            $currentTime = Carbon::createFromTimestamp($currentTimeStamp);
            //check if the current time is between 00:00 and 06:00
            if ($currentTime->isBetween($fromCarbon, $toCarbon) && $currentTime->hour >= $nightShiftBeginHour && $currentTime->hour < $nightShiftEndHour) {
                $nightShiftMinutes++;
            }
        }
        return $nightShiftMinutes;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function createTimeTrackerPdf(mixed $requestContent)
    {
//        $year = $requestContent['year'];
//        $month = $requestContent['month'];
//        $userId = $requestContent['userId'] ?? null;

        // Hole alle Arbeitszeiten für den angegebenen Monat und Jahr
//        $workingTimes = $this->getAllWorkingTimes($year, $month, $userId);

        $html = $this->twig->render('pdf/timetracking.html.twig', [
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');

        $dompdf->render();

        // name


        return $dompdf->output();
    }


    public function getDailyWorkingHoursForAllEmployees(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): array
    {
        $allTimeTrackEntities = $this->timeTrackerRepository->createQueryBuilder('t')
            ->where('t.timestamp >= :dateFrom')
            ->andWhere('t.timestamp <= :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        $processedData = $this->processTimeTrackEntities($allTimeTrackEntities);

        $dailyTotals = [];

        foreach ($processedData as $shift) {
            if ($shift['shiftStart'] === null || $shift['totalWorkingTime'] === null) {
                continue;
            }

            $date = $shift['shiftStart']->format('d.m.Y');

            if (!isset($dailyTotals[$date])) {
                $dailyTotals[$date] = 0;
            }

            list($hours, $minutes) = explode(':', $shift['totalWorkingTime']);
            $totalMinutes = ((int)$hours * 60) + (int)$minutes;
            $dailyTotals[$date] += $totalMinutes;
        }

        // Konvertiere Minuten in Stunden (Dezimal)
        foreach ($dailyTotals as $date => $minutes) {
            $dailyTotals[$date] = round($minutes / 60, 2);
        }

        uksort($dailyTotals, static function (string $a, string $b): int {
            $dateA = DateTimeImmutable::createFromFormat('d.m.Y', $a);
            $dateB = DateTimeImmutable::createFromFormat('d.m.Y', $b);

            return $dateA <=> $dateB; // aufsteigend: früh → spät
        });


        return $dailyTotals;
    }

    public function getYearlyWorkingHours(string $year, ?string $employeeId = null): array
    {
        $dateFrom = new DateTimeImmutable("$year-01-01 00:00:00");
        $dateTo = new DateTimeImmutable("$year-12-31 23:59:59");

        $criteria = ['timestamp >=' => $dateFrom, 'timestamp <=' => $dateTo];
        if ($employeeId) {
            $criteria['user'] = $employeeId;
        }

        $allTimeTrackEntities = $this->timeTrackerRepository->createQueryBuilder('t')
            ->where('t.timestamp >= :dateFrom')
            ->andWhere('t.timestamp <= :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo);

        if ($employeeId) {
            $allTimeTrackEntities->andWhere('t.user = :userId')
                ->setParameter('userId', $employeeId);
        }

        $allTimeTrackEntities = $allTimeTrackEntities->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        $processedData = $this->processTimeTrackEntities($allTimeTrackEntities);

        $totalMinutes = 0;
        foreach ($processedData as $shift) {
            if ($shift['totalWorkingTime'] !== null) {
                list($hours, $minutes) = explode(':', $shift['totalWorkingTime']);
                $totalMinutes += ((int)$hours * 60) + (int)$minutes;
            }
        }

        return [
            'year' => $year,
            'totalWorkingTime' => $this->formatMinutesToTime($totalMinutes),
            'totalWorkingHours' => round($totalMinutes / 60, 2)
        ];
    }

    /**
     * @throws \Exception
     */
    public function getMonthlyWorkingHours(string $year, string $month, ?string $employeeId = null): array
    {
        $dateFrom = new DateTimeImmutable("$year-$month-01 00:00:00");
        $dateTo = (new DateTimeImmutable($dateFrom->format('Y-m-t')))->setTime(23, 59, 59);

        $allTimeTrackEntities = $this->timeTrackerRepository->createQueryBuilder('t')
            ->where('t.timestamp >= :dateFrom')
            ->andWhere('t.timestamp <= :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo);

        if ($employeeId) {
            $allTimeTrackEntities->andWhere('t.user = :userId')
                ->setParameter('userId', $employeeId);
        }

        $allTimeTrackEntities = $allTimeTrackEntities->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        $processedData = $this->processTimeTrackEntities($allTimeTrackEntities);

        $totalMinutes = 0;
        foreach ($processedData as $shift) {
            if ($shift['totalWorkingTime'] !== null) {
                list($hours, $minutes) = explode(':', $shift['totalWorkingTime']);
                $totalMinutes += ((int)$hours * 60) + (int)$minutes;
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'totalWorkingTime' => $this->formatMinutesToTime($totalMinutes),
            'totalWorkingHours' => round($totalMinutes / 60, 2)
        ];
    }

    // In TimeTrackerService.php

    public function getYearlyWorkingHoursByEmployee(string $year): array
    {
        $dateFrom = new DateTimeImmutable("$year-01-01 00:00:00");
        $dateTo = new DateTimeImmutable("$year-12-31 23:59:59");

        $allTimeTrackEntities = $this->timeTrackerRepository->createQueryBuilder('t')
            ->where('t.timestamp >= :dateFrom')
            ->andWhere('t.timestamp <= :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        $processedData = $this->processTimeTrackEntities($allTimeTrackEntities);

        $employeeHours = [];

        foreach ($processedData as $shift) {
            $username = $shift['username'];

            if (!isset($employeeHours[$username])) {
                $employeeHours[$username] = 0;
            }

            if ($shift['totalWorkingTime'] !== null) {
                list($hours, $minutes) = explode(':', $shift['totalWorkingTime']);
                $totalMinutes = ((int)$hours * 60) + (int)$minutes;
                $employeeHours[$username] += $totalMinutes;
            }
        }

        foreach ($employeeHours as $username => $minutes) {
            $employeeHours[$username] = [
                'totalWorkingTime' => $this->formatMinutesToTime($minutes),
                'totalWorkingHours' => round($minutes / 60, 2)
            ];
        }

        return $employeeHours;
    }

    public function getYearlyWorkingHoursByEmployeeMonthly(float|bool|int|string $year)
    {
        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $dateFrom = new DateTimeImmutable(sprintf("%04d-%02d-01 00:00:00", $year, $month));
            $dateTo = (new DateTimeImmutable($dateFrom->format('Y-m-t')))->setTime(23, 59, 59);

            $allTimeTrackEntities = $this->timeTrackerRepository->createQueryBuilder('t')
                ->where('t.timestamp >= :dateFrom')
                ->andWhere('t.timestamp <= :dateTo')
                ->setParameter('dateFrom', $dateFrom)
                ->setParameter('dateTo', $dateTo)
                ->orderBy('t.id', 'ASC')
                ->getQuery()
                ->getResult();

            $processedData = $this->processTimeTrackEntities($allTimeTrackEntities);

            $employeeHours = [];

            foreach ($processedData as $shift) {
                $username = $shift['username'];

                if (!isset($employeeHours[$username])) {
                    $employeeHours[$username] = 0;
                }

                if ($shift['totalWorkingTime'] !== null) {
                    list($hours, $minutes) = explode(':', $shift['totalWorkingTime']);
                    $totalMinutes = ((int)$hours * 60) + (int)$minutes;
                    $employeeHours[$username] += $totalMinutes;
                }
            }

            foreach ($employeeHours as $username => $minutes) {
                $employeeHours[$username] = [
                    'totalWorkingTime' => $this->formatMinutesToTime($minutes),
                    'totalWorkingHours' => round($minutes / 60, 2)
                ];
            }

            $monthlyData[$month] = $employeeHours;
        }

        return $monthlyData;
    }
}
