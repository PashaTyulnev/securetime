<?php

namespace App\Validator;

use App\Entity\Shift;
use App\Repository\AbsenceRepository;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoAbsenceConflictValidator extends ConstraintValidator
{
    public function __construct(private readonly AbsenceRepository $absenceRepository) {}

    public function validate(mixed $shift, Constraint $constraint): void
    {
        if (!$shift instanceof Shift) {
            return;
        }

        $employee = $shift->getEmployee();
        $date     = $shift->getDate();

        if ($employee === null || $date === null) {
            return;
        }

        $shiftDate   = DateTimeImmutable::createFromMutable($date);
        $conflicting = $this->absenceRepository->findOverlapping($employee, $shiftDate, $shiftDate);

        if (count($conflicting) === 0) {
            return;
        }

        $absence      = $conflicting[0];
        $employeeName = $employee->getFirstName() . ' ' . $employee->getLastName();
        $dateStr      = $date->format('d.m.Y');
        $typeLabel    = $absence->getType()->choices()[$absence->getType()->value] ?? $absence->getType()->value;

        $this->context
            ->buildViolation(
                "{$employeeName} hat am {$dateStr} eine Abwesenheit ({$typeLabel}) und kann nicht eingeplant werden."
            )
            ->addViolation();
    }
}
