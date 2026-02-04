<?php

namespace App\Service\TimeTracker;

use App\Entity\TimeTracker;
use App\Entity\User;

use App\Repository\TimeTrackerRepository;
use App\Repository\ShiftRepository;
use App\Repository\EmployeeRepository;
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
                                public Environment            $twig,
                                public ShiftRepository        $shiftRepository,
                                public EmployeeRepository     $employeeRepository
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
            // Neuer Schichtbeginn - prüfe ob Mitarbeiter verplant ist
            $this->createTimeTrackerEntry($user, "checkin", true, null);
        } elseif (!$userIsCheckedIn && $userHasPause) {
            $lastMainCheckin = $this->getLastMainCheckin($user);
            $this->createTimeTrackerEntry($user, "checkin", false, $lastMainCheckin);

        } else {
            throw new \Exception('Benutzer ist schon eingecheckt');
        }

    }

    /**
     * @param \DateTimeImmutable|null $timestamp Optional: Spezifischer Timestamp. Wenn null, wird der aktuelle Zeitpunkt verwendet.
     */
    private function createTimeTrackerEntry(?UserInterface $user, $type, $isShiftStart, $parent, ?\DateTimeImmutable $timestamp = null): void
    {
        $timeTracker = new TimeTracker();
        $timeTracker->setUser($user);
        $timeTracker->setCreatedAt(new \DateTimeImmutable());
        
        // Wenn kein Timestamp übergeben wurde, verwende aktuellen Zeitpunkt
        if ($timestamp === null) {
            $timestamp = new \DateTimeImmutable();
        }
        
        $timeTracker->setTimestamp($timestamp);
        $timeTracker->setType($type);

        if ($type === "checkin" and $isShiftStart) {
            $timeTracker->setShiftStart(true);

            // Prüfe ob Mitarbeiter verplant ist und setze Facility + Abweichung
            if (!$user instanceof User) {
                throw new \Exception('Ungültiger Benutzer-Typ');
            }

            $employee = $user->getEmployee();
            if (!$employee) {
                throw new \Exception('Kein Mitarbeiter-Profil für diesen Benutzer gefunden');
            }

            // Prüfe ob an diesem Tag eine Schicht existiert
            $today = new \DateTime('today');
            $shifts = $this->shiftRepository->findByEmployeeAndDate($employee, $today);

            if (empty($shifts)) {
                throw new \Exception('Sie sind für heute nicht verplant. Bitte kontaktieren Sie Ihren Vorgesetzten.');
            }

            // Nimm die erste Schicht (falls mehrere am selben Tag)
            $shift = $shifts[0];
            $demandShift = $shift->getDemandShift();

            if (!$demandShift || !$demandShift->getFacility()) {
                throw new \Exception('Kein Objekt für die geplante Schicht gefunden');
            }

            $facility = $demandShift->getFacility();
            $timeTracker->setFacility($facility);

            // Berechne Abweichung zwischen Checkin-Zeit und geplanter Schicht-Zeit
            $plannedStartTime = $demandShift->getTimeFrom();
            if ($plannedStartTime) {
                // Kombiniere Datum und Zeit für geplanten Start
                $plannedDateTime = new \DateTime($today->format('Y-m-d') . ' ' . $plannedStartTime->format('H:i:s'));

                // Berechne Differenz in Minuten
                $diffInSeconds = $timestamp->getTimestamp() - $plannedDateTime->getTimestamp();
                $diffInMinutes = (int)round($diffInSeconds / 60);

                // Maximal 2 Stunden (120 Minuten) Abweichung erlauben
                if (abs($diffInMinutes) > 120) {
                    throw new \Exception(sprintf(
                        'Die Checkin-Zeit weicht um mehr als 2 Stunden von der geplanten Schicht-Zeit ab (Abweichung: %d Minuten). Bitte kontaktieren Sie Ihren Vorgesetzten.',
                        abs($diffInMinutes)
                    ));
                }

                $timeTracker->setTimeDeviation($diffInMinutes);
            }
        } else {
            $timeTracker->setShiftStart(false);
        }

        $timeTracker->setParent($parent);

        $this->em->persist($timeTracker);
        $this->em->flush();
    }

    /**
     * @param \DateTimeImmutable|null $checkoutTimestamp Optional: Spezifischer Checkout-Zeitpunkt. Wenn null, wird der aktuelle Zeitpunkt verwendet.
     */
    public function checkout(?UserInterface $user, ?\DateTimeImmutable $checkoutTimestamp = null): void
    {
        $userIsCheckedOut = $this->checkIfUserIsCheckedOut($user);

        if ($userIsCheckedOut) {
            throw new \Exception('Benutzer ist schon ausgecheckt');
        }

        // Letzten Main-Checkin (Beginn der Schicht) holen
        $lastMainCheckin = $this->getLastMainCheckin($user);

        if (!$lastMainCheckin) {
            throw new \Exception('Kein Schichtbeginn gefunden');
        }

        // Alle Einträge dieser Schicht laden
        $shiftEntries = $this->timeTrackerRepository->findShiftEntries($lastMainCheckin);

        // Checkout-Zeitpunkt: Wenn nicht gesetzt, verwende aktuellen Zeitpunkt
        if ($checkoutTimestamp === null) {
            $checkoutTimestamp = new \DateTimeImmutable();
        }

        // Validiere, dass der Checkout-Timestamp nach dem letzten Checkin liegt
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);
        if ($lastTimeTracker && $lastTimeTracker->getType() === 'checkin') {
            $lastCheckinTimestamp = $lastTimeTracker->getTimestamp();
            if ($checkoutTimestamp <= $lastCheckinTimestamp) {
                throw new \Exception(sprintf(
                    'Checkout-Timestamp (%s) muss nach dem letzten Checkin (%s) liegen.',
                    $checkoutTimestamp->format('Y-m-d H:i:s'),
                    $lastCheckinTimestamp->format('Y-m-d H:i:s')
                ));
            }
        }

        // Virtueller Checkout-Zeitpunkt für die Berechnung:
        // 1 Sekunde VOR dem eigentlichen Checkout, damit in der Übersicht
        // der echte Checkout als letztes Event bleibt.
        $virtualCheckoutTimestamp = $checkoutTimestamp->sub(new \DateInterval('PT1S'));

        // Füge einen virtuellen Checkout-Eintrag hinzu, um die aktuelle Schicht komplett auszuwerten
        // WICHTIG: Dieser Eintrag wird NICHT persistiert, sondern nur für die Berechnung genutzt.
        $virtualCheckout = new TimeTracker();
        $virtualCheckout->setUser($user);
        $virtualCheckout->setCreatedAt(new \DateTimeImmutable());
        $virtualCheckout->setTimestamp($virtualCheckoutTimestamp);
        $virtualCheckout->setType('checkout');
        $virtualCheckout->setShiftStart(false);
        $virtualCheckout->setParent($lastMainCheckin);

        $shiftEntriesWithVirtualCheckout = $shiftEntries;
        $shiftEntriesWithVirtualCheckout[] = $virtualCheckout;

        // Nutze die bestehende Logik zur Auswertung (eine Schicht)
        $processed = $this->processTimeTrackEntities($shiftEntriesWithVirtualCheckout);

        if (empty($processed)) {
            // Fallback: direkt auschecken
            $this->createTimeTrackerEntry($user, "checkout", false, $lastMainCheckin);
            return;
        }

        $summaryData = $this->calculateTotalsOfTimeSummary($processed);

        // Bereits erfasste Pausen & benötigte Pausen
        $totalPauseMinutes = $this->timeStringToMinutes($summaryData['totalPauseMinutes']);
        $requiredPauseMinutes = $summaryData['requiredPauseMinutes'];

        // Wenn zu wenig Pause genommen wurde, automatisch Pause nachtragen
        if ($totalPauseMinutes < $requiredPauseMinutes) {
            $missingPauseMinutes = $requiredPauseMinutes - $totalPauseMinutes;

            // Der Checkin nach der Pause mindestens 1 Minute vor Checkout (sinnvoller Abstand)
            $minCheckinBeforeCheckout = 1;
            $checkinAfterPause = $checkoutTimestamp->sub(new \DateInterval(sprintf('PT%dM', $minCheckinBeforeCheckout)));
            $pauseStart = $checkinAfterPause->sub(new \DateInterval(sprintf('PT%dM', $missingPauseMinutes)));

            // Konsistenz: Pause darf nicht vor Schichtbeginn liegen
            $mainCheckinTimestamp = $lastMainCheckin->getTimestamp();
            if ($pauseStart < $mainCheckinTimestamp) {
                $pauseStart = $mainCheckinTimestamp;
                $checkinAfterPause = $pauseStart->add(new \DateInterval(sprintf('PT%dM', $missingPauseMinutes)));
            }

            // Checkout muss strikt nach Checkin-Ende liegen (verhindert Inkonsistenzen)
            if ($checkoutTimestamp <= $checkinAfterPause) {
                $checkoutTimestamp = $checkinAfterPause->add(new \DateInterval('PT1M'));
            }

            // 1) Pause-Eintrag
            $pauseEntry = new TimeTracker();
            $pauseEntry->setUser($user);
            $pauseEntry->setCreatedAt(new \DateTimeImmutable());
            $pauseEntry->setTimestamp($pauseStart);
            $pauseEntry->setType('pause');
            $pauseEntry->setShiftStart(false);
            $pauseEntry->setParent($lastMainCheckin);
            $this->em->persist($pauseEntry);

            // 2) Checkin-Eintrag (Ende der Pause)
            $checkinEntry = new TimeTracker();
            $checkinEntry->setUser($user);
            $checkinEntry->setCreatedAt(new \DateTimeImmutable());
            $checkinEntry->setTimestamp($checkinAfterPause);
            $checkinEntry->setType('checkin');
            $checkinEntry->setShiftStart(false);
            $checkinEntry->setParent($lastMainCheckin);
            $this->em->persist($checkinEntry);

            $this->em->flush();
        }

        // Einheitliche Absicherung: Checkout-Zeitpunkt muss nach dem letzten Eintrag der Schicht liegen
        $shiftEntriesForValidation = $this->timeTrackerRepository->findShiftEntries($lastMainCheckin);
        $lastTimestampInShift = $lastMainCheckin->getTimestamp();
        foreach ($shiftEntriesForValidation as $entry) {
            $ts = $entry->getTimestamp();
            if ($ts > $lastTimestampInShift) {
                $lastTimestampInShift = $ts;
            }
        }
        $minCheckoutTimestamp = $lastTimestampInShift->add(new \DateInterval('PT1M'));
        if ($checkoutTimestamp <= $lastTimestampInShift) {
            $checkoutTimestamp = $minCheckoutTimestamp;
        }

        // Abschließend regulären Checkout-Eintrag speichern
        $this->createTimeTrackerEntry($user, "checkout", false, $lastMainCheckin, $checkoutTimestamp);
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

    /**
     * Löscht einen Zeiteintrag inkl. Parent (Haupt-Checkin) und aller zugehörigen Einträge der Schicht.
     * Erlaubt nur für Admins (Aufrufer muss ROLE_ADMIN prüfen).
     *
     * @param int $timeTrackerId ID eines Eintrags (Haupt-Checkin oder Kind)
     * @throws \InvalidArgumentException wenn Eintrag nicht gefunden
     */
    public function deleteTimeEntryWithParent(int $timeTrackerId): void
    {
        $entry = $this->timeTrackerRepository->find($timeTrackerId);
        if ($entry === null) {
            throw new \InvalidArgumentException('Zeiteintrag nicht gefunden.');
        }

        $mainCheckin = $entry->getParent() ?? $entry;
        $shiftEntries = $this->timeTrackerRepository->findShiftEntries($mainCheckin);

        // Kinder zuerst löschen (höchste ID zuerst), dann Parent
        usort($shiftEntries, static fn (TimeTracker $a, TimeTracker $b): int => $b->getId() <=> $a->getId());

        foreach ($shiftEntries as $e) {
            $this->em->remove($e);
        }
        $this->em->flush();
    }

    public function checkIfUserIsCheckedOut(?UserInterface $user): bool
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);

        if ($lastTimeTracker && $lastTimeTracker->getType() == 'checkout') {
            return true;
        }
        return false;
    }

    /**
     * Prüft, ob der Benutzer heute ausgecheckt hat (während der heutigen Schicht)
     */
    public function hasCheckedOutToday(?UserInterface $user): bool
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(['user' => $user], ['id' => 'DESC']);

        if (!$lastTimeTracker || $lastTimeTracker->getType() !== 'checkout') {
            return false;
        }

        // Prüfe, ob der Checkout heute war
        $checkoutTimestamp = $lastTimeTracker->getTimestamp();
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $checkoutTimestamp >= $today && $checkoutTimestamp < $tomorrow;
    }

    /**
     * Liefert den Zeitpunkt des letzten Check-outs, falls dieser heute erfolgt ist.
     *
     * @return \DateTimeImmutable|null Checkout-Zeitpunkt heute oder null
     */
    public function getLastCheckoutTimestampToday(?UserInterface $user): ?\DateTimeImmutable
    {
        $lastTimeTracker = $this->timeTrackerRepository->findOneBy(
            ['user' => $user, 'type' => 'checkout'],
            ['id' => 'DESC']
        );

        if (!$lastTimeTracker) {
            return null;
        }

        $checkoutTimestamp = $lastTimeTracker->getTimestamp();
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        if ($checkoutTimestamp < $today || $checkoutTimestamp >= $tomorrow) {
            return null;
        }

        return $checkoutTimestamp;
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

    /**
     * Erstellt einen manuellen Zeiteintrag für einen Mitarbeiter.
     * Ohne Endzeit wird nur ein Check-in erstellt (offene Schicht).
     *
     * @param User $user Der Benutzer/Mitarbeiter
     * @param \DateTimeImmutable $startDateTime Startzeitpunkt (Check-in)
     * @param \DateTimeImmutable|null $endDateTime Optional: Endzeitpunkt (Check-out). Null = nur Check-in.
     * @param int|null $facilityId Optional: Facility ID
     * @throws \Exception
     */
    public function createManualTimeEntry(User $user, \DateTimeImmutable $startDateTime, ?\DateTimeImmutable $endDateTime = null, ?int $facilityId = null): void
    {
        if ($this->checkIfUserIsCheckedIn($user)) {
            throw new \Exception('Es existiert bereits ein offener Zeiteintrag. Bitte schließen Sie diesen zuerst ab.');
        }

        $endGiven = $endDateTime !== null;

        if ($endGiven) {
            // Falls die Endzeit \"kleiner\" als die Startzeit ist, handelt es sich um eine Nachtschicht
            if ($endDateTime <= $startDateTime) {
                $endDateTime = $endDateTime->modify('+1 day');
            }
            $this->assertManualEntryDoesNotOverlap($user, $startDateTime, $endDateTime);
        } else {
            // Nur Check-in: Prüfen, ob Startzeit in einer bestehenden Schicht liegt
            $this->assertManualEntryDoesNotOverlap($user, $startDateTime, $startDateTime);
        }

        // Erstelle Checkin-Eintrag (Schichtbeginn)
        $checkinEntry = new TimeTracker();
        $checkinEntry->setUser($user);
        $checkinEntry->setCreatedAt(new \DateTimeImmutable());
        $checkinEntry->setTimestamp($startDateTime);
        $checkinEntry->setType('checkin');
        $checkinEntry->setShiftStart(true);
        $checkinEntry->setIsManualEntry(true);

        // Prüfe ob Mitarbeiter an diesem Tag verplant ist (optional für manuelle Einträge)
        $shiftDate = new \DateTime($startDateTime->format('Y-m-d'));
        $employee = $user->getEmployee();
        if ($employee) {
            $shifts = $this->shiftRepository->findByEmployeeAndDate($employee, $shiftDate);
            if (!empty($shifts)) {
                $shift = $shifts[0];
                $demandShift = $shift->getDemandShift();
                if ($demandShift && $demandShift->getFacility()) {
                    $checkinEntry->setFacility($demandShift->getFacility());
                }
            }
        }

        $this->em->persist($checkinEntry);
        $this->em->flush();

        if (!$endGiven) {
            // Nur Check-in erstellt, fertig
            return;
        }

        // Berechne Schichtdauer in Minuten
        $shiftDuration = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
        $shiftDurationMinutes = (int)($shiftDuration / 60);

        // Berechne benötigte Pause basierend auf Schichtdauer (wie in calculateTotalsOfTimeSummary)
        $requiredPauseMinutes = 0;
        if ($shiftDurationMinutes > 9 * 60) {
            $requiredPauseMinutes = 45;
        } elseif ($shiftDurationMinutes > 6 * 60) {
            $requiredPauseMinutes = 30;
        }

        // Wenn Pause benötigt wird, füge sie automatisch ein (1 Stunde vor Checkout)
        if ($requiredPauseMinutes > 0) {
            $pauseEnd = $endDateTime->sub(new \DateInterval('PT60M'));
            $pauseStart = $pauseEnd->sub(new \DateInterval(sprintf('PT%dM', $requiredPauseMinutes)));

            // Konsistenz: Pause darf nicht vor Schichtbeginn liegen
            if ($pauseStart < $startDateTime) {
                $pauseStart = $startDateTime;
                $pauseEnd = $startDateTime->add(new \DateInterval(sprintf('PT%dM', $requiredPauseMinutes)));
            }
            if ($pauseEnd >= $endDateTime) {
                $pauseEnd = $endDateTime->sub(new \DateInterval('PT1M'));
            }

            $pauseEntry = new TimeTracker();
            $pauseEntry->setUser($user);
            $pauseEntry->setCreatedAt(new \DateTimeImmutable());
            $pauseEntry->setTimestamp($pauseStart);
            $pauseEntry->setType('pause');
            $pauseEntry->setShiftStart(false);
            $pauseEntry->setParent($checkinEntry);
            $pauseEntry->setIsManualEntry(true);
            $this->em->persist($pauseEntry);

            $checkinAfterPause = new TimeTracker();
            $checkinAfterPause->setUser($user);
            $checkinAfterPause->setCreatedAt(new \DateTimeImmutable());
            $checkinAfterPause->setTimestamp($pauseEnd);
            $checkinAfterPause->setType('checkin');
            $checkinAfterPause->setShiftStart(false);
            $checkinAfterPause->setParent($checkinEntry);
            $checkinAfterPause->setIsManualEntry(true);
            $this->em->persist($checkinAfterPause);
        }

        // Erstelle Checkout-Eintrag
        $checkoutEntry = new TimeTracker();
        $checkoutEntry->setUser($user);
        $checkoutEntry->setCreatedAt(new \DateTimeImmutable());
        $checkoutEntry->setTimestamp($endDateTime);
        $checkoutEntry->setType('checkout');
        $checkoutEntry->setShiftStart(false);
        $checkoutEntry->setParent($checkinEntry);
        $checkoutEntry->setIsManualEntry(true);
        $this->em->persist($checkoutEntry);

        $this->em->flush();
    }

    /**
     * Prüft, ob der Zeitraum [start, end] mit einer bestehenden Schicht des Benutzers überlappt.
     *
     * @throws \Exception wenn Überlappung gefunden
     */
    private function assertManualEntryDoesNotOverlap(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $ranges = $this->timeTrackerRepository->getTimeRangesForUser($user);
        $now = new \DateTimeImmutable();

        foreach ($ranges as $range) {
            $existingStart = $range['start'];
            $existingEnd = $range['end'] ?? $now;

            if ($start < $existingEnd && $end > $existingStart) {
                throw new \Exception('Der Zeitraum überlappt mit einer bestehenden Schicht.');
            }
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
        $filtered = $this->filterByMonthAndYear($output, $year, $month);

        // Neueste Einträge zuerst (nach Schichtbeginn absteigend)
        usort($filtered, static function (array $a, array $b): int {
            $startA = $a['shiftStart'] ?? null;
            $startB = $b['shiftStart'] ?? null;
            if ($startA === null && $startB === null) {
                return 0;
            }
            if ($startA === null) {
                return 1;
            }
            if ($startB === null) {
                return -1;
            }
            return $startB <=> $startA;
        });

        return $filtered;
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
        $userId = $user->getId();


        if ($entity->isShiftStart()) {
            $isManualEntry = $entity->isManualEntry();
            $this->handleShiftStart($output, $id, $user->getEmployee()->getFullName(), $timestamp, $userId, $isManualEntry);
        } elseif (isset($output[$parent]) && $type === 'checkout') {
            $this->handleShiftEnd($output[$parent], $timestamp, $entity);
        } elseif (isset($output[$parent]) && $type === 'pause') {
            $this->handlePause($output[$parent], $timestamp);
        } elseif (isset($output[$parent]) && $type === 'checkin') {
            $this->handleCheckIn($output[$parent], $timestamp);
        }



    }

    private function handleShiftStart(array &$output, int $id, string $username, $timestamp, ?int $userId = null, ?bool $isManualEntry = false): void
    {
        $output[$id] = [
            'timeTrackerId' => $id,
            'username' => $username,
            'shiftStart' => $timestamp,
            'shiftEnd' => null,
            'shiftDuration' => null,
            'pauseDuration' => new \DateInterval('PT0S'),
            'pausePeriods' => [],
            'userId' => $userId,
            'autoCheckOut' => false,
            'isManualEntry' => $isManualEntry,
        ];
    }

    private function handleShiftEnd(array &$entry, $timestamp, $checkoutEntity): void
    {
        $entry['shiftEnd'] = $timestamp;
        $entry['shiftDuration'] = $entry['shiftStart']->diff($timestamp);
        // Füge autoCheckOut Flag hinzu, falls vorhanden
        if ($checkoutEntity instanceof TimeTracker) {
            $entry['autoCheckOut'] = $checkoutEntity->isAutoCheckOut();
        } else {
            $entry['autoCheckOut'] = false;
        }
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
            $userId = null;
        } else {
            $userName = $userTimeTrackingData[0]->username;
            // Versuche userId aus den Daten zu extrahieren
            $userId = $userTimeTrackingData[0]->userId ?? null;
        }

        $userTimeTrackingData = $this->calculateNightShiftWorkingTime($userTimeTrackingData,23,6);

        $summaryData = $this->calculateTotalsOfTimeSummary($userTimeTrackingData);

        $totalWorkingMinutes = $summaryData['totalWorkingMinutes'];
        $totalPauseMinutes = $summaryData['totalPauseMinutes'];
        $totalShiftDuration = $summaryData['totalShiftDuration'];

        $totalNightShiftPause = $summaryData['totalNightShiftPause'];
        $totalNightShiftWorkingTime = $summaryData['totalNightShiftWorkingTime'];
        $totalNightShiftWithoutPause = $summaryData['totalNightShiftWithoutPause'];
        $requiredPause = $summaryData['requiredPause'];
        $pauseDeficit = $summaryData['pauseDeficit'];
        $pauseDeficitMinutes = $summaryData['pauseDeficitMinutes'];

        $dateRange = $this->getDateRange($userTimeTrackingData);

        // Berechne Soll-Zeiten aus vergebenen Schichten
        $plannedHours = $this->calculatePlannedHoursFromShifts(
            $dateRange['minDate'],
            $dateRange['maxDate'],
            $userId
        );

        // Berechne Differenz zwischen Ist und Soll
        $istMinutes = $this->timeStringToMinutes($totalWorkingMinutes);
        $sollMinutes = $this->timeStringToMinutes($plannedHours);
        $diffMinutes = $istMinutes - $sollMinutes;
        $diffHours = $this->formatMinutesToTime(abs($diffMinutes));
        $diffSign = $diffMinutes >= 0 ? '+' : '-';

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
            'plannedHours' => $plannedHours,
            'differenceHours' => $diffSign . $diffHours,
            'differenceMinutes' => $diffMinutes,
            'requiredPause' => $requiredPause,
            'pauseDeficit' => $pauseDeficit,
            'pauseDeficitMinutes' => $pauseDeficitMinutes,
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
            // Unterstützt sowohl Array- als auch Objekt-Struktur
            $workingTime = is_array($timeEntry)
                ? ($timeEntry['totalWorkingTime'] ?? null)
                : ($timeEntry->totalWorkingTime ?? null); // Format "hh:mm"

            $pauseTime = is_array($timeEntry)
                ? ($timeEntry['pauseDuration'] ?? null)
                : ($timeEntry->pauseDuration ?? null); // Format "hh:mm"

            $shiftTime = is_array($timeEntry)
                ? ($timeEntry['shiftDuration'] ?? null)
                : ($timeEntry->shiftDuration ?? null); // Format "hh:mm"

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

            $nightShiftPause = is_array($timeEntry)
                ? ($timeEntry['nightShiftPause'] ?? null)
                : ($timeEntry->nightShiftPause ?? null);

            if ($nightShiftPause !== null) {
                list($hours, $minutes) = explode(":", $nightShiftPause);
                $totalNightShiftPause += ((int)$hours * 60) + (int)$minutes;
            }

            $nightShiftTotal = is_array($timeEntry)
                ? ($timeEntry['nightShiftTotal'] ?? null)
                : ($timeEntry->nightShiftTotal ?? null);

            if ($nightShiftTotal !== null) {
                list($hours, $minutes) = explode(":", $nightShiftTotal);
                $totalNightShiftWorkingTime += ((int)$hours * 60) + (int)$minutes;

            }

            $nightShiftWithoutPause = is_array($timeEntry)
                ? ($timeEntry['nightShiftWithoutPause'] ?? null)
                : ($timeEntry->nightShiftWithoutPause ?? null);

            if ($nightShiftWithoutPause !== null) {
                list($hours, $minutes) = explode(":", $nightShiftWithoutPause);
                $totalNightShiftWithoutPause += ((int)$hours * 60) + (int)$minutes;
            }
        }

        // Gesetzliche Mindestpausen nach Gesamtarbeitszeit
        // > 6 bis 9 Stunden: mindestens 30 Minuten
        // > 9 Stunden: mindestens 45 Minuten
        $requiredPauseMinutes = 0;
        if ($totalShiftDuration > 9 * 60) {
            $requiredPauseMinutes = 45;
        } elseif ($totalShiftDuration > 6 * 60) {
            $requiredPauseMinutes = 30;
        }

        $pauseDeficitMinutes = $totalPauseMinutes - $requiredPauseMinutes;
        $pauseDeficitSign = $pauseDeficitMinutes >= 0 ? '+' : '-';
        $pauseDeficitFormatted = $pauseDeficitSign . $this->formatMinutesToTime(abs($pauseDeficitMinutes));

        return [
            'totalWorkingMinutes' => $this->formatMinutesToTime($totalWorkingMinutes),
            'totalPauseMinutes' => $this->formatMinutesToTime($totalPauseMinutes),
            'totalShiftDuration' => $this->formatMinutesToTime($totalShiftDuration),
            'totalNightShiftPause' => $this->formatMinutesToTime($totalNightShiftPause),
            'totalNightShiftWorkingTime' => $this->formatMinutesToTime($totalNightShiftWorkingTime),
            'totalNightShiftWithoutPause' => $this->formatMinutesToTime($totalNightShiftWithoutPause),
            'requiredPause' => $this->formatMinutesToTime($requiredPauseMinutes),
            'requiredPauseMinutes' => $requiredPauseMinutes,
            'pauseDeficit' => $pauseDeficitFormatted,
            'pauseDeficitMinutes' => $pauseDeficitMinutes,
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
    public function validateInputDates(
        User $user,
        mixed $timeTrackerId,
        \DateTimeImmutable $shiftStart,
        \DateTimeImmutable $shiftEnd,
        $timeTrackerEntryCheckin,
        $timeTrackerEntryCheckout
    ): void {

        // ==============================
        // Vorheriger Main-Checkin
        // ==============================
        $previousTimeTrackerMainCheckinEntry =
            $this->timeTrackerRepository->findPreviousMainCheckinEntry($user, $timeTrackerId);

        if ($previousTimeTrackerMainCheckinEntry !== null) {

            $previousTimeTrackerMainCheckoutEntry =
                $this->timeTrackerRepository->findOneBy([
                    'parent' => $previousTimeTrackerMainCheckinEntry->getId(),
                    'type'   => 'checkout'
                ]);

            if ($previousTimeTrackerMainCheckoutEntry !== null) {
                $previousCheckoutDateTime = $previousTimeTrackerMainCheckoutEntry->getTimestamp();

                if ($shiftStart < $previousCheckoutDateTime) {
                    throw new \DateMalformedStringException(
                        'Schichtanfang darf nicht vor dem letzten Schichtende liegen'
                    );
                }
            }
        }

        // ==============================
        // Nächster Main-Checkin
        // ==============================
        $nextTimeTrackerMainCheckinEntry =
            $this->timeTrackerRepository->findNextMainCheckinEntry($user, $timeTrackerId);

        if ($nextTimeTrackerMainCheckinEntry !== null) {
            $nextTimeTrackerMainCheckinEntryDate = $nextTimeTrackerMainCheckinEntry->getTimestamp();

            if ($shiftEnd > $nextTimeTrackerMainCheckinEntryDate) {
                throw new \DateMalformedStringException(
                    'Schichtende darf nicht nach dem nächsten Schichtanfang liegen'
                );
            }
        }

        // ==============================
        // Grundvalidierung
        // ==============================
        if ($shiftEnd < $shiftStart) {
            throw new \DateMalformedStringException(
                'Ende der Schicht darf nicht vor dem Start sein'
            );
        }

        // ==============================
        // Persistieren
        // ==============================
        $timeTrackerEntryCheckin->setTimestamp($shiftStart);
        $timeTrackerEntryCheckout->setTimestamp($shiftEnd);
    }


    /**
     * Zählt wie viele Minuten in der Nacht gearbeitet wurden ( 23:00 - 06:00)
     */
    private function calculateNightShiftWorkingTime(mixed $userTimeTrackingData, $nightShiftBeginHour, $nightShiftEndHour): array
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


    public function getNightShiftMinutesBetweenTimestamps(Carbon $fromCarbon, Carbon $toCarbon, $nightShiftBeginHour, $nightShiftEndHour): int
    {
        $nightShiftMinutes = 0;
        $current = $fromCarbon->copy();

        while ($current < $toCarbon) {
            $hour = $current->hour;

            //check if new day
            if ($nightShiftEndHour > $nightShiftBeginHour) {
                //Normalfall z.B. 22-06 Uhr
                if ($hour < $nightShiftEndHour && $hour >= $nightShiftBeginHour) {
                    $nightShiftMinutes++;
                }
            } else {
                //Über Mitternacht z.B. 23-06 Uhr
                if ($hour < $nightShiftEndHour || $hour >= $nightShiftBeginHour) {
                    $nightShiftMinutes++;
                }
            }

            $current->addMinute();
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

    /**
     * @throws \Exception
     */
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

    /**
     * Berechnet die Soll-Zeiten aus vergebenen Schichten für einen Mitarbeiter im angegebenen Zeitraum.
     * $dateFrom / $dateTo können Strings (\"Y-m-d\"), DateTime oder DateTimeImmutable sein.
     */
    public function calculatePlannedHoursFromShifts($dateFrom, $dateTo, ?int $userId = null): string
    {
        // Normalisiere Datumswerte
        if ($dateFrom === null || $dateTo === null) {
            return $this->formatMinutesToTime(0);
        }

        if (is_string($dateFrom)) {
            $dateFrom = new \DateTimeImmutable($dateFrom);
        } elseif ($dateFrom instanceof \DateTime) {
            $dateFrom = \DateTimeImmutable::createFromMutable($dateFrom);
        }

        if (is_string($dateTo)) {
            $dateTo = new \DateTimeImmutable($dateTo);
        } elseif ($dateTo instanceof \DateTime) {
            $dateTo = \DateTimeImmutable::createFromMutable($dateTo);
        }

        // Wenn userId nicht gesetzt ist, können wir keine Soll-Zeiten berechnen
        if ($userId === null) {
            return $this->formatMinutesToTime(0);
        }

        // Finde den Employee anhand der userId
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user || !$user->getEmployee()) {
            return $this->formatMinutesToTime(0);
        }

        $employee = $user->getEmployee();

        // Konvertiere DateTimeImmutable zu DateTime für das Repository
        $dateFromDateTime = \DateTime::createFromImmutable($dateFrom);
        $dateToDateTime = \DateTime::createFromImmutable($dateTo);

        // Hole alle vergebenen Schichten für diesen Mitarbeiter im Zeitraum
        $shifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $dateFromDateTime->format('Y-m-d'),
            $dateToDateTime->format('Y-m-d')
        );

        $totalMinutes = 0;

        foreach ($shifts as $shift) {
            $demandShift = $shift->getDemandShift();
            if ($demandShift === null) {
                continue;
            }

            $timeFrom = $demandShift->getTimeFrom();
            $timeTo = $demandShift->getTimeTo();

            if ($timeFrom === null || $timeTo === null) {
                continue;
            }

            // Berechne die Dauer zwischen timeFrom und timeTo
            $fromHour = (int)$timeFrom->format('H');
            $fromMinute = (int)$timeFrom->format('i');
            $toHour = (int)$timeTo->format('H');
            $toMinute = (int)$timeTo->format('i');

            // Berechne die Minuten seit Mitternacht
            $fromMinutes = ($fromHour * 60) + $fromMinute;
            $toMinutes = ($toHour * 60) + $toMinute;

            // Wenn timeTo vor timeFrom liegt, geht die Schicht über Mitternacht
            if ($toMinutes < $fromMinutes) {
                // Schicht geht über Mitternacht: (24*60 - fromMinutes) + toMinutes
                $shiftMinutes = (24 * 60 - $fromMinutes) + $toMinutes;
            } else {
                // Normale Schicht: toMinutes - fromMinutes
                $shiftMinutes = $toMinutes - $fromMinutes;
            }

            $totalMinutes += $shiftMinutes;
        }

        return $this->formatMinutesToTime($totalMinutes);
    }

    /**
     * Konvertiert einen Zeitstring (HH:MM) in Minuten
     */
    private function timeStringToMinutes(string $timeString): int
    {
        if (empty($timeString) || $timeString === '00:00') {
            return 0;
        }

        $parts = explode(':', $timeString);
        if (count($parts) !== 2) {
            return 0;
        }

        $hours = (int)$parts[0];
        $minutes = (int)$parts[1];

        return ($hours * 60) + $minutes;
    }
}
