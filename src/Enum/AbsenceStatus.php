<?php

namespace App\Enum;

enum AbsenceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function choices(): array
    {
        return [
            self::Pending->value => 'Offen',
            self::Approved->value => 'Genehmigt',
            self::Rejected->value => 'Abgelehnt',
        ];
    }
}
