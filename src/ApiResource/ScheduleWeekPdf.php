<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

use App\Provider\ScheduleWeekPdfProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/schedule/week/pdf',
            name: 'generate_schedule_week_pdf',
            provider: ScheduleWeekPdfProvider::class,
        )
    ]
)]
class ScheduleWeekPdf
{
    public int $week;
    public int $year;
    public int $weekSpan;
    public int $facilityId;
}
