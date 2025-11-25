<?php

namespace App\Validator\Constraint;

use App\Validator\EmployeeQualificationValidator;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class EmployeeQualificationConstraint extends Constraint
{
    public string $message = 'Der Mitarbeiter ist nicht für diese Schicht qualifiziert.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return EmployeeQualificationValidator::class;
    }
}
