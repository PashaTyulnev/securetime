<?php

namespace App\Validator;

use App\Entity\DemandShift;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use DateTime;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ShiftCustomRulesValidator extends ConstraintValidator
{

    const MAX_WORKING_DAYS_IN_ROW = 7;

    public function __construct(
        private readonly ShiftRepository $shiftRepository
    )
    {
    }

    public function validate(mixed $shift, Constraint $constraint): void
    {
        if (!$shift instanceof Shift) {
            return;
        }

        $demandShift = $shift->getDemandShift();
        $date = $shift->getDate();

        // Prüfung ob an diesem Tag überhaupt eine Schicht sein muss
        if (!$this->isDemandShiftActiveOnDate($demandShift, $date)) {
            $this->addDemandShiftNotActiveViolation($demandShift, $date);
            return;
        }

        // Maximale Anzahl an Mitarbeitern für diese Schicht prüfen
        if ($this->maxEmployeesExceeded($demandShift, $date, $shift)) {
            $this->addMaxEmployeesViolation($demandShift);
        }

        $this->maxHoursInMonthExceeded($shift);
        $this->checkWorkingDaysInRow($shift);
    }

    private function isDemandShiftActiveOnDate($demandShift, \DateTime $date): bool
    {
        $dayOfWeek = (int)$date->format('N'); // 1 = Montag, 7 = Sonntag

        $activeDays = [
            1 => $demandShift->isOnMonday(),
            2 => $demandShift->isOnTuesday(),
            3 => $demandShift->isOnWednesday(),
            4 => $demandShift->isOnThursday(),
            5 => $demandShift->isOnFriday(),
            6 => $demandShift->isOnSaturday(),
            7 => $demandShift->isOnSunday(),
        ];

        return $activeDays[$dayOfWeek] ?? false;
    }

    private function maxEmployeesExceeded($demandShift, \DateTime $date, Shift $newShift): bool
    {
        $maxEmployees = $demandShift->getAmountEmployees();
        $currentAssignedCount = $this->countAssignedEmployees($demandShift, $date, $newShift);

        return $currentAssignedCount >= $maxEmployees;
    }

    private function countAssignedEmployees($demandShift, \DateTime $date, Shift $excludeShift): int
    {
        $existingShifts = $this->shiftRepository->findByDemandShiftAndDate($demandShift, $date);

        // Filtere die neue Schicht raus (falls Update statt Create)
        $existingShifts = array_filter($existingShifts, function (Shift $shift) use ($excludeShift) {
            return $shift->getId() !== $excludeShift->getId();
        });

        return count($existingShifts);
    }

    private function addMaxEmployeesViolation($demandShift): void
    {
        $demandShiftName = $demandShift->getName();
        $maxEmployees = $demandShift->getAmountEmployees();

        $this->context
            ->buildViolation("Die maximale Personenanzahl ({$maxEmployees}) für '{$demandShiftName}' wurde bereits erreicht")
            ->addViolation();
    }

    private function addDemandShiftNotActiveViolation($demandShift, \DateTime $date): void
    {
        $demandShiftName = $demandShift->getName();
        $dayName = $this->getGermanDayName($date);

        $this->context
            ->buildViolation("Die Schicht '{$demandShiftName}' ist am {$dayName} nicht aktiv")
            ->addViolation();
    }

    private function getGermanDayName(\DateTime $date): string
    {
        $days = [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        ];

        $dayOfWeek = (int)$date->format('N');

        return $days[$dayOfWeek] ?? 'Unbekannt';
    }

    private function maxHoursInMonthExceeded(Shift $shift): void
    {
        $maxHoursInMonth = $this->getMaxMonthHours($shift);

        if ($maxHoursInMonth === null) {
            return;
        }

        $alreadyAssignedShifts = $this->getAssignedShiftsInMonth($shift);
        $assignedHours = $this->calculateTotalHours($alreadyAssignedShifts);

        if ($assignedHours > $maxHoursInMonth) {
            $employeeName = $shift->getEmployee()?->getFirstName() . ' ' . $shift->getEmployee()?->getLastName();
            $this->addMaxHoursInMonthViolation($maxHoursInMonth, $employeeName);
        }
    }

    private function getMaxMonthHours(Shift $shift): ?float
    {
        return $shift->getEmployee()
            ?->getContracts()
            ?->first()
            ?->getMaxMonthHours();
    }

    private function getAssignedShiftsInMonth(Shift $shift): array
    {
        $firstDayOfMonth = $shift->getDate()->format('Y-m-01');
        $lastDayOfMonth = $shift->getDate()->format('Y-m-t');

        return $this->shiftRepository->getAllAssignedShiftsInRange(
            $shift->getEmployee(),
            $firstDayOfMonth,
            $lastDayOfMonth
        );
    }

    private function calculateTotalHours(array $shifts): float
    {
        $totalHours = 0;

        foreach ($shifts as $shift) {
            $totalHours += $this->calculateShiftDuration($shift);
        }

        return $totalHours;
    }

    private function calculateShiftDuration(Shift $shift): float
    {
        $demandShift = $shift->getDemandShift();
        $timeFrom = $demandShift->getTimeFrom();
        $timeTo = $demandShift->getTimeTo();

        if ($timeTo <= $timeFrom) {
            return $this->calculateOvernightDuration($timeFrom, $timeTo);
        }

        return ($timeTo->getTimestamp() - $timeFrom->getTimestamp()) / 3600;
    }

    private function calculateOvernightDuration(\DateTime $timeFrom, \DateTime $timeTo): float
    {
        $endOfDay = (new DateTime())->setTime(23, 59, 59);
        $startOfDay = (new DateTime())->setTime(0, 0, 0);

        $firstPart = ($endOfDay->getTimestamp() - $timeFrom->getTimestamp()) / 3600;
        $secondPart = ($timeTo->getTimestamp() - $startOfDay->getTimestamp()) / 3600;

        return $firstPart + $secondPart;
    }

    private function addMaxHoursInMonthViolation($maxHoursInMonth, $employeeName): void
    {

        $this->context
            ->buildViolation("Mitarbeiter '{$employeeName}' hat die maximalen Stunden im Monat von {$maxHoursInMonth} Stunden überschritten.")
            ->addViolation();
    }

    /**
     * @throws \Exception
     */
    private function checkWorkingDaysInRow(Shift $shift): void
    {
        $employee = $shift->getEmployee();
        $date = $shift->getDate(); // DateTime, Datum des Shift-Starts
        $maxWorkingDaysInRow = self::MAX_WORKING_DAYS_IN_ROW;

        // Zeitfenster: eine Woche zurück + eine Woche vorwärts (oder abhängig von MAX)
        $oneWeekAgo = (clone $date)->modify('-' . $maxWorkingDaysInRow . ' days');
        $oneWeekAhead = (clone $date)->modify('+' . $maxWorkingDaysInRow . ' days');

        // Past = strikt vor dem aktuellen Datum, Future = strikt nach dem aktuellen Datum
        $pastShifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $oneWeekAgo->format('Y-m-d'),
            (clone $date)->modify('-1 day')->format('Y-m-d')
        );

        $futureShifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            (clone $date)->modify('+1 day')->format('Y-m-d'),
            $oneWeekAhead->format('Y-m-d')
        );

        // --- Normalisiere auf eindeutige Kalenderdaten (String 'Y-m-d') ---
        $pastDates = [];
        foreach ($pastShifts as $s) {
            $pastDates[] = $s->getDate()->format('Y-m-d');
        }
        $pastDates = array_values(array_unique($pastDates));
        sort($pastDates); // aufsteigend

        $futureDates = [];
        foreach ($futureShifts as $s) {
            $futureDates[] = $s->getDate()->format('Y-m-d');
        }
        $futureDates = array_values(array_unique($futureDates));
        sort($futureDates); // aufsteigend

        //////////////////////////////////////////////////////////////
        // 1) Berechne aufeinanderfolgende Kalendertage (inkl. heute)
        //////////////////////////////////////////////////////////////
        $consecutiveDays = 1; // zählt der aktuelle Tag

        // rückwärts: prüfe, ob date -1, -2, ... in $pastDates vorhanden ist
        for ($i = 1; $i < $maxWorkingDaysInRow; $i++) {
            $check = (clone $date)->modify("-{$i} days")->format('Y-m-d');
            if (in_array($check, $pastDates, true)) {
                $consecutiveDays++;
            } else {
                break;
            }
        }

        // vorwärts: prüfe, ob date +1, +2, ... in $futureDates vorhanden ist
        for ($i = 1; $i < $maxWorkingDaysInRow; $i++) {
            $check = (clone $date)->modify("+{$i} days")->format('Y-m-d');
            if (in_array($check, $futureDates, true)) {
                $consecutiveDays++;
            } else {
                break;
            }
        }

        //////////////////////////////////////////////////////////////
        // 2) Wenn Grenze erreicht oder überschritten -> 24h-Pause prüfen
        //////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////
// 2) Wenn Grenze erreicht oder überschritten -> 24h-Pause prüfen
//////////////////////////////////////////////////////////////

        if ($consecutiveDays >= $maxWorkingDaysInRow) {
            // finde letzte Schicht VOR dem aktuellen Datum (wichtig: die mit der spätesten Endzeit)
            $lastShift = null;
            $lastShiftEnd = null; // DateTime

            // Durchsuche alle past shifts (originalen Objekte), bestimme Endzeit und nimm die größte Endzeit
            foreach ($pastShifts as $ps) {
                // nur wirklich vor dem aktuellen Datum (sicherheitshalber)
                if ($ps->getDate()->format('Y-m-d') >= $date->format('Y-m-d')) {
                    continue;
                }

                // berechne Ende dieser Schicht (Datum + timeTo; falls Overnight -> +1 day)
                $psDate = $ps->getDate()->format('Y-m-d');
                $psEnd = new \DateTime($psDate . ' ' . $ps->getDemandShift()->getTimeTo()->format('H:i'));

                if ($this->isOvernightShift($ps)) {
                    $psEnd->modify('+1 day');
                }

                // falls mehrere Schichten am selben Tag -> wir wollen die späteste Endzeit
                if ($lastShiftEnd === null || $psEnd->getTimestamp() > $lastShiftEnd->getTimestamp()) {
                    $lastShiftEnd = $psEnd;
                    $lastShift = $ps;
                }
            }

            if ($lastShift && $lastShiftEnd) {
                // Start des neuen Shifts (Datum + timeFrom)
                $newShiftStart = new \DateTime(
                    $date->format('Y-m-d') . ' ' . $shift->getDemandShift()->getTimeFrom()->format('H:i')
                );

                // Prüfe ob genau am MAX. Tag -> dann muss Ende der aktuellen Schicht + 24h vor nächstem Start liegen
                if ($consecutiveDays === $maxWorkingDaysInRow) {
                    // Berechne Ende der AKTUELLEN Schicht
                    $currentShiftEnd = new \DateTime(
                        $date->format('Y-m-d') . ' ' . $shift->getDemandShift()->getTimeTo()->format('H:i')
                    );
                    if ($this->isOvernightShift($shift)) {
                        $currentShiftEnd->modify('+1 day');
                    }

                    // Prüfe ob nach dieser Schicht eine weitere folgt (am nächsten Tag)
                    $nextDayDate = (clone $currentShiftEnd)->format('Y-m-d');

                    if (in_array($nextDayDate, $futureDates, true)) {
                        // Finde die früheste Schicht am nächsten Tag
                        $nextShiftStart = null;
                        foreach ($futureShifts as $fs) {
                            if ($fs->getDate()->format('Y-m-d') === $nextDayDate) {
                                $fsStart = new \DateTime(
                                    $fs->getDate()->format('Y-m-d') . ' ' . $fs->getDemandShift()->getTimeFrom()->format('H:i')
                                );
                                if ($nextShiftStart === null || $fsStart->getTimestamp() < $nextShiftStart->getTimestamp()) {
                                    $nextShiftStart = $fsStart;
                                }
                            }
                        }

                        if ($nextShiftStart) {
                            $diffHours = ($nextShiftStart->getTimestamp() - $currentShiftEnd->getTimestamp()) / 3600;
                            if ($diffHours < 24) {
                                $this->context
                                    ->buildViolation(sprintf(
                                        "Nach %d Arbeitstagen in Folge müssen mindestens 24 Stunden Ruhezeit eingehalten werden. Zwischen letzter und neuer Schicht liegen nur %.1f Stunden.",
                                        $maxWorkingDaysInRow,
                                        $diffHours
                                    ))
                                    ->addViolation();

                                return;
                            }
                        }
                    }
                } else {
                    // Mehr als MAX -> prüfe zwischen letzter vergangener und aktueller Schicht
                    $diffHours = ($newShiftStart->getTimestamp() - $lastShiftEnd->getTimestamp()) / 3600;

                    if ($diffHours < 24) {
                        $this->context
                            ->buildViolation(sprintf(
                                "Nach %d Arbeitstagen in Folge müssen mindestens 24 Stunden Ruhezeit eingehalten werden. Zwischen letzter und neuer Schicht liegen nur %.1f Stunden.",
                                $consecutiveDays,
                                $diffHours
                            ))
                            ->addViolation();

                        return;
                    }
                }
            }

        }

        //////////////////////////////////////////////////////////////
        // 3) Wenn streng > MAX -> direkte Verletzung (falls nötig)
        //////////////////////////////////////////////////////////////
        if ($consecutiveDays > $maxWorkingDaysInRow) {
            $this->context
                ->buildViolation(sprintf(
                    "Mitarbeiter %s %s hat die maximale Anzahl von %d Arbeitstagen in Folge erreicht.",
                    $employee->getFirstName(),
                    $employee->getLastName(),
                    $maxWorkingDaysInRow
                ))
                ->addViolation();
        }
    }




    private function isOvernightShift(Shift $shift): bool
    {
        $demandShift = $shift->getDemandShift();
        $timeFrom = $demandShift->getTimeFrom();
        $timeTo = $demandShift->getTimeTo();

        return $timeTo <= $timeFrom;
    }


}
