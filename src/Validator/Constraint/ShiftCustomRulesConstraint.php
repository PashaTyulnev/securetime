<?php

namespace App\Validator\Constraint;

use App\Validator\ShiftCustomRulesValidator;
use App\Validator\ShiftTimeRulesValidator;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ShiftCustomRulesConstraint extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return ShiftCustomRulesValidator::class;
    }
}
