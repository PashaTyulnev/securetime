<?php

namespace App\Validator;

use App\Entity\Facility;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoDemandShiftsValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var Facility $value */

        if (!$value instanceof Facility) {
            return;
        }

        if ($value->getDemandShifts()->count() > 0) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
