<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

use App\Provider\ScheduleMonthPdfProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/schedule/month/pdf',
            name: 'generate_schedule_pdf',
            provider: ScheduleMonthPdfProvider::class,
        )
    ]
)]
class ScheduleMonthPdf
{
    public int $month;
    public int $year;
}
