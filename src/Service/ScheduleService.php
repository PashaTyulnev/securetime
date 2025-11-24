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

    public function getShiftsForEmployeesInDateRange(array $datesRange)
    {
        $firstDate = DateTime::createFromFormat('d.m.Y', $datesRange[0]['date']);
        $lastDate = DateTime::createFromFormat('d.m.Y', end($datesRange)['date']);

        $firstDate->setTime(0, 0, 0);
        $lastDate->setTime(23, 59, 59);

        $results = $this->shiftRepository->findShiftsInDateRange($firstDate, $lastDate);

        $shifts = $this->serializer->serialize(
            $results,
            'jsonld',
            ['groups' => ['shift:read']]
        );

        return json_decode($shifts, true);


    }
}
