<?php
namespace App\Validator\Constraint;

use App\Validator\NoDemandShiftsValidator;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NoDemandShiftsConstraint extends Constraint
{
    public string $message = 'Diese Einrichtung kann nicht gelöscht werden, da noch Bedarfsschichten existieren.';

    public function __construct(?string $message = null, ?array $groups = null, $payload = null)
    {
        parent::__construct([], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return NoDemandShiftsValidator::class;
    }
}
