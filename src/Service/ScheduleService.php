<?php

namespace App\Service;

use DateTime;

class ScheduleService
{

    public function buildWeekDaysRange(int $year, int $week): array
    {
        $date = new DateTime();
        $date->setISODate($year, $week, 1); // Montag der Woche

        $today = new DateTime();
        $todayFormatted = $today->format('d.m.Y');

        $weekDays = [];
        $germanDays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $germanMonths = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        for ($i = 0; $i < 7; $i++) {
            $dateFormatted = $date->format('d.m.Y');
            $isToday = $dateFormatted === $todayFormatted;

            $day = $date->format('d');
            $month = $germanMonths[(int)$date->format('m') - 1];
            $shortName = "$day. $month";

            $weekDays[] = [
                'name' => $germanDays[$i],
                'date' => $dateFormatted,
                'shortName' => $shortName,
                'isToday' => $isToday
            ];
            $date->modify('+1 day');
        }

        return $weekDays;
    }
}
