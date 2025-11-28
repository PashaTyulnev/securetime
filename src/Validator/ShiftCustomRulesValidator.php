<?php

namespace App\Validator;

use App\Entity\DemandShift;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ShiftCustomRulesValidator extends ConstraintValidator
{
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

        if (!$this->isDemandShiftActiveOnDate($demandShift, $date)) {
            $this->addDemandShiftNotActiveViolation($demandShift, $date);
            return;
        }

        if ($this->maxEmployeesExceeded($demandShift, $date, $shift)) {
            $this->addMaxEmployeesViolation($demandShift);
        }

        $this->maxHoursInMonthExceeded($demandShift, $shift);
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

    private function maxHoursInMonthExceeded(?DemandShift $demandShift, Shift $shift)
    {
        $contracts = $shift->getEmployee()?->getContracts();
        $onlyOneContract = $contracts?->first();

        $maxHoursInMonth = $onlyOneContract?->getMaxMonthHours();

        $firstDayOfMonth = $shift->getDate()->format('Y-m-01');
        $lastDayOfMonth = $shift->getDate()->format('Y-m-t');

        $alreadyAssignedShifts = $this->shiftRepository->getAllAssignedShiftsInRange(
            $shift->getEmployee(),
            $firstDayOfMonth,
            $lastDayOfMonth
        );

        $assignedHours = 0;

        foreach ($alreadyAssignedShifts as $shift) {

            $demandShift = $shift->getDemandShift();

            $timeFrom = $demandShift->getTimeFrom();
            $timeTo = $demandShift->getTimeTo();

            $isOvernight = $timeTo <= $timeFrom;

            if ($isOvernight) {
                $endOfDay = (new \DateTime())->setTime(23, 59, 59);
                $startOfDay = (new \DateTime())->setTime(0, 0, 0);

                $duration = ($endOfDay->getTimestamp() - $timeFrom->getTimestamp()) / 3600;
                $duration += ($timeTo->getTimestamp() - $startOfDay->getTimestamp()) / 3600;
                $assignedHours += $duration;
            } else {
                $duration = ($timeTo->getTimestamp() - $timeFrom->getTimestamp()) / 3600;
                $assignedHours += $duration;
            }
        }

        if ($assignedHours > $maxHoursInMonth) {
            $employeeName = $shift->getEmployee()?->getFirstName() . ' ' . $shift->getEmployee()?->getLastName();
            $this->addMaxHoursInMonthViolation($maxHoursInMonth, $employeeName);
        }
    }

    private function addMaxHoursInMonthViolation($maxHoursInMonth, $employeeName): void
    {

        $this->context
            ->buildViolation("Mitarbeiter '{$employeeName}' hat die maximalen Stunden im Monat von {$maxHoursInMonth} Stunden überschritten.")
            ->addViolation();
    }
}
