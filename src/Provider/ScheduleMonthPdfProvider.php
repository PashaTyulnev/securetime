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

class ScheduleMonthPdfProvider implements ProviderInterface
{
    public function __construct(private RequestStack $requestStack, readonly Environment $twig,
                                readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ScheduleService $scheduleService) {}
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {

        $request = $this->requestStack->getCurrentRequest();
        $month = (int) $request->query->get('month');
        $year = (int) $request->query->get('year');

        if (!$month || !$year) {
            return new Response('Month and Year are required', 400);
        }


        $pdfContent = $this->generatePdf($month, $year);

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                // 'attachment' sorgt für direkten Download, optional 'inline' für im Browser öffnen
                'Content-Disposition' => 'attachment; filename="schedule_' . $month . '_' . $year . '.pdf"',
                'Content-Length' => strlen($pdfContent),
            ]
        );
    }

    private function generatePdf(int $month, int $year): string
    {
        $dompdf = new Dompdf();

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildMonthDaysRange((int)$year, (int)$month);

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange);

        $html = $this->twig->render('pdf/month_schedule.html.twig', [
            'employees' => $employees,
            'facilities' => $facilities,
            'datesRange' => $datesRange,
            'shifts' => $shifts,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
