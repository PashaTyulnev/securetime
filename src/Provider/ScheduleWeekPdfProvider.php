<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ScheduleMonthPdf;
use App\Entity\Employee;
use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ScheduleService;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class ScheduleWeekPdfProvider implements ProviderInterface
{
    public function __construct(private RequestStack $requestStack,
                                readonly Environment $twig,
                                readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ScheduleService $scheduleService) {}
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {

        $request = $this->requestStack->getCurrentRequest();

        $week = (int) $request->query->get('week');
        $year = (int) $request->query->get('year');
        $weekSpan = (int) $request->query->get('weekSpan',1);
        $facilityId = $request->query->get('facilityId', null);

        if($facilityId === 'null'){
            $facilityId = null;
        }

        if (!$week || !$year) {
            return new Response('Week and Year are required', 400);
        }


        $pdfContent = $this->generatePdf($week, $year,$weekSpan, $facilityId);

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                // 'attachment' sorgt für direkten Download, optional 'inline' für im Browser öffnen
                'Content-Disposition' => 'attachment; filename="schedule_' . $week . '_' . $year . '.pdf"',
                'Content-Length' => strlen($pdfContent),
            ]
        );
    }

    private function generatePdf(int $week, int $year, $weekSpan, $facilityId = null): string
    {
        $dompdf = new Dompdf();

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildWeekDaysRange((int)$year, (int)$week, (int)$weekSpan);

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange,$facilityId);

        $selectedFacility = null;

        if($facilityId !== null){
            $selectedFacility = $this->facilityService->getFacilityById($facilityId);
        }

        $html = $this->twig->render('pdf/week_schedule.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
            'shifts' => $shifts,
            'dateFrom' => $datesRange[0]['date'],
            'dateTo' => end($datesRange)['date'],
            'selectedFacility' => $selectedFacility,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
