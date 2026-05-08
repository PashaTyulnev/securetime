<?php

namespace App\Enum;

enum AbsenceType: string
{
    case Vacation = 'vacation';
    case Sick = 'sick';
    case Unavailable = 'unavailable';

    public static function choices(): array
    {
        return [
            self::Vacation->value => 'Urlaub',
            self::Sick->value => 'Krank',
            self::Unavailable->value => 'Nicht verfügbar',
        ];
    }
}
