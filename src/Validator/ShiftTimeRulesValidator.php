<?php

namespace App\Validator;

use App\Entity\Shift;
use App\Repository\ShiftRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ShiftTimeRulesValidator extends ConstraintValidator
{

    public function __construct(readonly ShiftRepository $shiftRepository)
    {

    }

    public function validate(mixed $shift, Constraint $constraint)
    {
        if (!$shift instanceof Shift) {
            return;
        }

        $employee = $shift->getEmployee();
        $employeeFullName = $employee->getFirstName() . " " . $employee->getLastName();

        $newAssignment = $shift->getDemandShift();
        $newFrom = $newAssignment->getTimeFrom();
        $newTo   = $newAssignment->getTimeTo();

        $date = $shift->getDate();

        // Neue Schicht normalisieren
        [$newStart, $newEnd] = $this->normalizeShift($date, $newFrom, $newTo);

        // Alle Schichten am Tag holen
        $employeeShifts = $this->shiftRepository
            ->findByEmployeeAndDate($employee, $shift->getDate());

        foreach ($employeeShifts as $employeeShift) {

            $empDate = $employeeShift->getDate();
            $empAssignment = $employeeShift->getDemandShift();

            $empFrom = $empAssignment->getTimeFrom();
            $empTo   = $empAssignment->getTimeTo();

            // Existierende Schicht normalisieren
            [$existStart, $existEnd] = $this->normalizeShift($empDate, $empFrom, $empTo);

            // 1) SCHICHTÜBERSCHNEIDUNG
            if ($newStart < $existEnd && $newEnd > $existStart) {
                $this->context
                    ->buildViolation("$employeeFullName hat bereits eine Schicht in diesem Zeitraum")
                    ->addViolation();
                return;
            }

            // 2) 11-STUNDEN RUHEZEIT
            $minRestInterval = new \DateInterval('PT11H');

            // Fall A: Neue Schicht beginnt zu früh nach der alten
            $restAfterExisting = $existEnd->add(new \DateInterval('PT0S')); // clone workaround
            $restAfterExisting = (clone $existEnd)->add($minRestInterval);

            if ($newStart < $restAfterExisting) {
                $this->context
                    ->buildViolation("$employeeFullName muss mindestens 11 Stunden Ruhezeit nach der vorherigen Schicht haben")
                    ->addViolation();
                return;
            }

            // Fall B: Neue Schicht endet zu spät vor der alten
            $restBeforeExisting = (clone $newEnd)->add($minRestInterval);

            if ($restBeforeExisting > $existStart) {
                $this->context
                    ->buildViolation("$employeeFullName muss mindestens 11 Stunden Ruhezeit vor der nächsten Schicht haben")
                    ->addViolation();
                return;
            }
        }
    }


    private function normalizeShift(\DateTime $date, \DateTime $from, \DateTime $to): array
    {
        $start = (clone $date)->setTime(
            (int)$from->format('H'),
            (int)$from->format('i'),
            0
        );

        $end = (clone $date)->setTime(
            (int)$to->format('H'),
            (int)$to->format('i'),
            0
        );

        // Nachtschicht
        if ($to < $from) {
            $end->modify('+1 day');
        }

        return [$start, $end];
    }

}
