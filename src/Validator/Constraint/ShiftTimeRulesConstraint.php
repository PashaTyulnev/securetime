<?php
namespace App\Validator\Constraint;

use App\Validator\EmployeeQualificationValidator;
use App\Validator\ShiftTimeRulesValidator;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ShiftTimeRulesConstraint extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return ShiftTimeRulesValidator::class;
    }
}
