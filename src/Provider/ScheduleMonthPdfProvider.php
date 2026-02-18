<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ScheduleMonthPdf;
use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ScheduleService;
use Dompdf\Dompdf;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class ScheduleMonthPdfProvider implements ProviderInterface
{
    public function __construct(private RequestStack $requestStack,
                                readonly Environment $twig,
                                readonly EmployeeService $employeeService,
                                readonly FacilityService $facilityService,
                                readonly ScheduleService $scheduleService,
                                readonly Security $security) {}
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {

        $request = $this->requestStack->getCurrentRequest();
        $month = (int) $request->query->get('month');
        $year = (int) $request->query->get('year');
        $facilityId = $request->query->get('facilityId', null);

        if($facilityId === 'null'){
            $facilityId = null;
        }

        if (!$month || !$year) {
            return new Response('Month and Year are required', 400);
        }


        $pdfContent = $this->generatePdf($month, $year, $facilityId);


        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                // 'attachment' sorgt für direkten Download, optional 'inline' für im Browser öffnen
                'Content-Disposition' => 'attachment; filename="dienstplan_monat_' . $month . '_' . $year . '.pdf"',
                'Content-Length' => strlen($pdfContent),
            ]
        );
    }

    private function generatePdf(int $month, int $year, $facilityId = null): string
    {
        $dompdf = new Dompdf();

        $employees = $this->employeeService->getAllEmployees();
        $facilities = $this->facilityService->getAllFacilities();
        $datesRange = $this->scheduleService->buildMonthDaysRange($year, $month);

        $shifts = $this->scheduleService->getShiftsForEmployeesInDateRange($datesRange, $facilityId);

        $selectedFacility = null;

        if($facilityId !== null){
            $selectedFacility = $this->facilityService->getFacilityById($facilityId);
        }

        $currentRoles = $this->security->getUser()?->getRoles() ?? [];

        if (!in_array('ROLE_ADMIN', $currentRoles, true) && !in_array('ROLE_PARTNER_ADMIN', $currentRoles, true)) {
            $currentEmployeeIri = $this->employeeService->getCurrentEmployeeIri();

            if ($currentEmployeeIri !== null) {
                $shifts = array_values(array_filter(
                    $shifts,
                    static fn (array $shift): bool => ($shift['employee'] ?? null) === $currentEmployeeIri
                ));

                $employees = array_values(array_filter(
                    $employees,
                    static fn (array $employee): bool => ($employee['@id'] ?? null) === $currentEmployeeIri
                ));
            }
        }

        $relevantEmployees = $this->employeeService->filterOnlyShiftedEmployees($employees, $shifts);
        $html = $this->twig->render('pdf/month_schedule.html.twig', [
            'employees' => $relevantEmployees,
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
