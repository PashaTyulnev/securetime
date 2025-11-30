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
        $date = $shift->getDate();

        $maxWorkingDaysInRow = self::MAX_WORKING_DAYS_IN_ROW;

        // Zeitfenster: eine Woche zurück + eine Woche vorwärts
        $oneWeekAgo = (clone $date)->modify('-' . $maxWorkingDaysInRow . ' days');
        $oneWeekAhead = (clone $date)->modify('+' . $maxWorkingDaysInRow . ' days');

        // Schichten holen
        $pastShifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $oneWeekAgo->format('Y-m-d'),
            $date->format('Y-m-d')
        );

        $futureShifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $employee,
            $date->format('Y-m-d'),
            $oneWeekAhead->format('Y-m-d')
        );

        // Sortieren (wichtig!)
        usort($pastShifts, fn($a, $b) => $a->getDate() <=> $b->getDate());
        usort($futureShifts, fn($a, $b) => $a->getDate() <=> $b->getDate());

        //////////////////////////////////////////////////////////////
        // 1. Tage in Folge berechnen
        //////////////////////////////////////////////////////////////

        $consecutiveDays = 1; // aktueller Tag zählt

        // Rückwärts
        $daysBefore = 0;
        for ($i = count($pastShifts) - 1; $i >= 0; $i--) {
            $daysBefore++;
            $expected = (clone $date)->modify("-{$daysBefore} days");

            if ($pastShifts[$i]->getDate()->format('Y-m-d') === $expected->format('Y-m-d')) {
                $consecutiveDays++;
            } else {
                break;
            }
        }

        // Vorwärts
        $daysAfter = 0;
        foreach ($futureShifts as $futureShift) {
            // gleiche Datum = gleiche Schicht → überspringen
            if ($futureShift->getDate()->format('Y-m-d') === $date->format('Y-m-d')) {
                continue;
            }

            $daysAfter++;
            $expected = (clone $date)->modify("+{$daysAfter} days");

            if ($futureShift->getDate()->format('Y-m-d') === $expected->format('Y-m-d')) {
                $consecutiveDays++;
            } else {
                break;
            }
        }

        //////////////////////////////////////////////////////////////
        // 2. Wenn 7 Tage erreicht → 24h Pause prüfen
        //////////////////////////////////////////////////////////////


        if ($consecutiveDays >= $maxWorkingDaysInRow) {

            // letzte Schicht VOR aktueller finden
            $lastShift = end($pastShifts);

            if ($lastShift) {
                $lastDate = $lastShift->getDate();
                $lastTo   = $lastShift->getDemandShift()->getTimeTo();
                $lastFrom = $lastShift->getDemandShift()->getTimeFrom();

                // Ende der letzten Schicht
                $lastShiftEnd = new DateTime($lastDate->format('Y-m-d') . ' ' . $lastTo->format('H:i'));

                // Falls Overnight → endet am Folgetag
                if ($this->isOvernightShift($lastShift)) {
                    $lastShiftEnd->modify('+1 day');
                }

                // Start des neuen Shifts
                $newShiftStart = new DateTime(
                    $date->format('Y-m-d')
                    . ' '
                    . $shift->getDemandShift()->getTimeFrom()->format('H:i')
                );

                // Stunden berechnen
                $diffHours = ($newShiftStart->getTimestamp() - $lastShiftEnd->getTimestamp()) / 3600;

                if ($diffHours < 24) {
                    $this->context
                        ->buildViolation(sprintf(
                            "Nach 7 Arbeitstagen in Folge müssen mindestens 24 Stunden Ruhezeit eingehalten werden. Zwischen letzter und neuer Schicht liegen nur %.1f Stunden.",
                            $diffHours
                        ))
                        ->addViolation();

                    return;
                }
            }
        }


        //////////////////////////////////////////////////////////////
        // 3. Normale 7-Tage-Regel
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
