<?php

namespace App\Exception;

class AbsenceException extends \RuntimeException
{
    public static function dateRangeInvalid(): self
    {
        return new self('Das Startdatum darf nicht nach dem Enddatum liegen.');
    }

    public static function overlappingAbsenceExists(): self
    {
        return new self('Im gewählten Zeitraum existiert bereits eine offene oder genehmigte Abwesenheit für diesen Mitarbeiter.');
    }

    public static function cannotApproveNonPending(): self
    {
        return new self('Nur Abwesenheiten mit Status „Offen" können genehmigt werden.');
    }

    public static function cannotRejectNonPending(): self
    {
        return new self('Nur Abwesenheiten mit Status „Offen" können abgelehnt werden.');
    }
}
