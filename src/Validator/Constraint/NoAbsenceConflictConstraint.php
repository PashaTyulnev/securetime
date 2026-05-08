<?php

namespace App\Validator\Constraint;

use App\Validator\NoAbsenceConflictValidator;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class NoAbsenceConflictConstraint extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return NoAbsenceConflictValidator::class;
    }
}
