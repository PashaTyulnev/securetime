<?php

namespace App\Validator;

use App\Entity\Shift;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EmployeeQualificationValidator extends ConstraintValidator
{

    public function validate(mixed $shift, Constraint $constraint)
    {
        if (!$shift instanceof Shift) {
            return;
        }

        $employee = $shift->getEmployee();
        $demandShift = $shift->getDemandShift();

        $employeeQualificationRank = $employee->getQualification()->getRank();
        $demandQualificationRank = $demandShift->getRequiredQualification()->getRank();

        if ($employeeQualificationRank < $demandQualificationRank) {
            $this->context->buildViolation($employee->getFirstName() . " ". $employee->getLastName() . " " . "benötigt mindestens die Qualifikation '" . $demandShift->getRequiredQualification()->getTitle() . "' für diese Schicht.")
                ->addViolation();
        }

    }
}
