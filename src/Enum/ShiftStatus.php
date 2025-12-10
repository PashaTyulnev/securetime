<?php
namespace App\Enum;

enum ShiftStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public static function choices(): array
    {
        return [
            self::Pending->value => 'Pending',
            self::Accepted->value => 'Accepted',
            self::Declined->value => 'Declined',
        ];
    }
}
