<?php

namespace App\Service;

use App\Repository\ShiftRepository;
use DateTime;
use Symfony\Component\Serializer\SerializerInterface;

class ScheduleService
{

    public function __construct(readonly ShiftRepository $shiftRepository,readonly SerializerInterface $serializer)
    {

    }
    public function buildWeekDaysRange(int $year, int $week, int $weekSpan = 1): array
    {
        $date = new DateTime();
        $date->setISODate($year, $week, 1); // Montag der Startwoche

        $today = new DateTime();
        $todayFormatted = $today->format('d.m.Y');

        $weekDays = [];
        $germanDays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $germanMonths = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        $totalDays = 7 * $weekSpan;

        for ($i = 0; $i < $totalDays; $i++) {
            $dateFormatted = $date->format('d.m.Y');
            $isToday = $dateFormatted === $todayFormatted;

            $dayNumber = (int)$date->format('N') - 1; // 0 = Montag
            $day = $date->format('d');
            $month = $germanMonths[(int)$date->format('m') - 1];
            $shortName = "$day. $month";

            $weekDays[] = [
                'name' => $germanDays[$dayNumber],
                'date' => $dateFormatted,
                'shortName' => $shortName,
                'isToday' => $isToday,
                'week' => (int)$date->format('W'),
                'year' => (int)$date->format('o'),
            ];

            $date->modify('+1 day');
        }

        return $weekDays;
    }


    public function getShiftsForEmployeesInDateRange(array $datesRange, $facilityId = null): array
    {
        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $firstDate->setTime(0, 0, 0);
        $lastDate->setTime(23, 59, 59);

        $results = $this->shiftRepository->findShiftsInDateRange($firstDate, $lastDate,$facilityId);

        $shifts = $this->serializer->serialize(
            $results,
            'jsonld',
            ['groups' => ['shift:read']]
        );

        return json_decode($shifts, true);


    }

    public function buildMonthDaysRange(int $year, int $month)
    {
        $date = new DateTime();
        $date->setDate($year, $month, 1); // Erster Tag des Monats

        $today = new DateTime();
        $todayFormatted = $today->format('d.m.Y');

        $monthDays = [];
        $germanDays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $germanMonths = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        $daysInMonth = (int)$date->format('t');

        for ($i = 0; $i < $daysInMonth; $i++) {
            $dateFormatted = $date->format('d.m.Y');
            $isToday = $dateFormatted === $todayFormatted;

            $day = $date->format('d');
            $monthName = $germanMonths[(int)$date->format('m') - 1];
            $shortName = "$day. $monthName";

            $monthDays[] = [
                'name' => $germanDays[(int)$date->format('w')],
                'date' => $dateFormatted,
                'shortName' => $shortName,
                'isToday' => $isToday
            ];
            $date->modify('+1 day');
        }

        return $monthDays;
    }
}
