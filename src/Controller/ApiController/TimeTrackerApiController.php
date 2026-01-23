<?php

namespace App\Controller\ApiController;

use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use App\Repository\TimeTrackerRepository;
use App\Service\EmployeeService;
use App\Service\TimeTracker\TimeTrackerService;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;

#[IsGranted('ROLE_USER')]
class TimeTrackerApiController extends AbstractController
{

    public function __construct(
        readonly TimeTrackerService $timeTrackerService,
        readonly ShiftRepository $shiftRepository,
        readonly EmployeeRepository $employeeRepository,
        readonly TimeTrackerRepository $timeTrackerRepository,
        readonly KernelInterface $kernel,
        readonly EmployeeService $employeeService,
    ) {

    }

    #[Route('/api/timetracker/checkin', name: 'api_time_tracker_checkin', methods: ['GET'])]
    public function checkin(): JsonResponse
    {

        try {
            $this->timeTrackerService->checkin($this->getUser());
            return new JsonResponse(['message' => 'Benutzer wurde erfolgreich eingecheckt'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/checkout', name: 'api_time_tracker_checkout', methods: ['GET'])]
    public function checkout(): JsonResponse
    {

        try {
            $this->timeTrackerService->checkout($this->getUser());
            return new JsonResponse(['message' => 'Benutzer wurde erfolgreich ausgecheckt'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/pausein', name: 'api_time_tracker_pausein', methods: ['GET'])]
    public function pausein(): JsonResponse
    {

        try {
            $this->timeTrackerService->pause($this->getUser());
            return new JsonResponse(['message' => 'Pause wurde gestartet'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/status', name: 'api_time_tracker_status', methods: ['GET'])]
    public function getCurrentWorkingStatus(): JsonResponse
    {

        try {
            $timestamp = $this->timeTrackerService->getLastTimeStamp($this->getUser());
            return new JsonResponse($timestamp, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/overview_all', name: 'api_time_tracker_overview_all', methods: ['GET'])]
    public function getAllWorkingTimes(Request $request): JsonResponse
    {

        $year = $request->query->get("year");
        $month = $request->query->get("month");
        $userId = $request->query->get("userId");

        try {
            $allWorkingTimes = $this->timeTrackerService->getAllWorkingTimes($year, $month, $userId);

            return new JsonResponse($allWorkingTimes, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/user_totals', name: 'api_time_tracker_user_totals', methods: ['POST'])]
    public function getUserTotals(Request $request): JsonResponse
    {
        $userTimeTrackingData = json_decode($request->getContent());

        try {
            $timeTrackingSummaryInformation = $this->timeTrackerService->calculateUserTotals($userTimeTrackingData);
            return new JsonResponse($timeTrackingSummaryInformation, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/last_user_information', name: 'api_time_tracker_last_user_information', methods: ['GET'])]
    public function getLastUserInformation(Request $request): JsonResponse
    {

        try {
            $lastUserWorkingTimes = $this->timeTrackerService->getLastUserWorkingTimes();

            return new JsonResponse($lastUserWorkingTimes, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[Route('/api/timetracker/availableMonths', name: 'api_time_tracker_available_months', methods: ['GET'])]
    public function getAllAvailableMonths(Request $request): JsonResponse
    {


        try {
            $allAvailableMonths = $this->timeTrackerService->getAllAvailableMonths();

            return new JsonResponse($allAvailableMonths, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/timetracker/saveChangedTimeEntry', name: 'api_time_tracker_save_change', methods: ['POST'])]
    public function saveChangedTimeEntry(Request $request)
    {

        $requestContent = json_decode($request->getContent());

        try {
            $this->timeTrackerService->saveChangedTimeEntry($requestContent);
            return new JsonResponse(['message' => 'Zeiteintrag wurde erfolgreich geändert'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[Route(path: '/api/timetracker/pdf', name: 'timetrackerpdf', methods: ['POST'])]
    public function createTimeTrackerPdf(Request $request): Response
    {
        $trackedTimes = json_decode($request->getContent());

        $totals = $this->timeTrackerService->calculateUserTotals($trackedTimes);
        $totals = (object)$totals;

        // Füge Objekt-Informationen zu jedem Zeiteintrag hinzu
        $trackedTimesWithFacilities = $this->enrichTrackedTimesWithFacilities($trackedTimes);

        // Logo als base64 für Dompdf (zuverlässiger als Dateipfad)
        $logoPath = $this->kernel->getProjectDir() . '/public/logo.png';
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $imageData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $html = $this->renderView('pdf/timetracking.html.twig', [
            'trackedTimes' => $trackedTimesWithFacilities,
            'totals' => $totals,
            'logoBase64' => $logoBase64,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $dateFrom = $totals->dateFrom;
        $year = date('Y', strtotime($dateFrom));
        $month = date('m', strtotime($dateFrom));
        $title = "Zeiterfassung_" . $totals->username . "_" . $month . "_" . $year;

        $pdfContent = $dompdf->output();

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"',
            ]
        );
    }

    #[Route('/api/timetracker/yearly_totals', name: 'api_time_tracker_yearly_totals', methods: ['GET'])]
    public function getYearlyTotals(Request $request): JsonResponse
    {
        $year = $request->query->get('year');
        $employeeId = $request->query->get('employeeId');

        if (!$year) {
            return new JsonResponse(['message' => 'Jahr ist erforderlich'], 400);
        }

        try {
            $yearlyTotals = $this->timeTrackerService->getYearlyWorkingHours($year, $employeeId);
            return new JsonResponse($yearlyTotals, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/timetracker/monthly_totals', name: 'api_time_tracker_monthly_totals', methods: ['GET'])]
    public function getMonthlyTotals(Request $request): JsonResponse
    {
        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $employeeId = $request->query->get('employeeId');

        if (!$year || !$month) {
            return new JsonResponse(['message' => 'Jahr und Monat sind erforderlich'], 400);
        }

        try {
            $monthlyTotals = $this->timeTrackerService->getMonthlyWorkingHours($year, $month, $employeeId);
            return new JsonResponse($monthlyTotals, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/timetracker/yearly_hours_by_employee', name: 'api_time_tracker_yearly_hours_by_employee', methods: ['GET'])]
    public function getYearlyHoursByEmployee(Request $request): JsonResponse
    {
        $year = $request->query->get('year');

        if (!$year) {
            return new JsonResponse(['message' => 'Jahr ist erforderlich'], 400);
        }

        try {
            $yearlyHoursByEmployee = $this->timeTrackerService->getYearlyWorkingHoursByEmployee($year);
            return new JsonResponse($yearlyHoursByEmployee, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/timetracker/yearly_hours_per_month_all', name: 'yearly_hours_per_month_all', methods: ['GET'])]
    public function getYearlyHoursPerMonthAll(Request $request): JsonResponse
    {
        $year = $request->query->get('year');

        if (!$year) {
            return new JsonResponse(['message' => 'Jahr ist erforderlich'], 400);
        }

        try {
            $yearlyHoursByEmployee = $this->timeTrackerService->getYearlyWorkingHoursByEmployeeMonthly($year);
            return new JsonResponse($yearlyHoursByEmployee, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/timetracker/employees', name: 'api_time_tracker_employees', methods: ['GET'])]
    public function getEmployees(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getAllEmployees(false);
            return new JsonResponse($employees, 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/timetracker/create_manual_entry', name: 'api_time_tracker_create_manual_entry', methods: ['POST'])]
    public function createManualTimeEntry(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId']) || !isset($data['date']) || !isset($data['timeFrom']) || !isset($data['timeTo'])) {
            return new JsonResponse(['message' => 'Benutzer, Datum, Start- und Endzeit sind erforderlich'], 400);
        }

        try {
            $employee = $this->employeeRepository->find($data['userId']);
            if (!$employee || !$employee->getUser()) {
                return new JsonResponse(['message' => 'Mitarbeiter nicht gefunden'], 400);
            }

            $user = $employee->getUser();

            // Kombiniere Datum und Zeit zu DateTimeImmutable
            $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['timeFrom']);
            $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['timeTo']);

            if (!$startDateTime || !$endDateTime) {
                return new JsonResponse(['message' => 'Ungültiges Datum oder Zeitformat'], 400);
            }

            $this->timeTrackerService->createManualTimeEntry($user, $startDateTime, $endDateTime);
            return new JsonResponse(['message' => 'Zeiteintrag wurde erfolgreich erstellt'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Fügt Objekt-Informationen zu den Zeiteinträgen hinzu
     * Holt die Facility direkt aus dem TimeTracker-Eintrag (beim Checkin gespeichert)
     */
    private function enrichTrackedTimesWithFacilities(array $trackedTimes): array
    {
        foreach ($trackedTimes as $trackedTime) {
            $trackedTime->facilityName = '-';

            // Prüfe ob timeTrackerId vorhanden ist (für direkten Zugriff auf TimeTracker)
            if (isset($trackedTime->timeTrackerId)) {
                $timeTracker = $this->timeTrackerRepository->find($trackedTime->timeTrackerId);
                if ($timeTracker && $timeTracker->getFacility()) {
                    // Facility direkt aus dem TimeTracker-Eintrag holen
                    $trackedTime->facilityName = $timeTracker->getFacility()->getTitle();
                    continue;
                }
            }

            // Fallback: Alte Methode über Schicht (für alte Einträge ohne Facility)
            if (!isset($trackedTime->shiftStart) || !isset($trackedTime->username)) {
                continue;
            }

            $shiftDate = new \DateTime($trackedTime->shiftStart->date);
            $username = $trackedTime->username;

            // Finde Employee über Username
            $employee = $this->employeeRepository->findOneByUsername($username);
            if (!$employee) {
                continue;
            }

            // Finde Shifts für diesen Mitarbeiter an diesem Tag
            $shifts = $this->shiftRepository->findByEmployeeAndDate($employee, $shiftDate);

            // Nimm das erste Objekt (falls mehrere Shifts am selben Tag)
            if (!empty($shifts)) {
                $shift = $shifts[0];
                $demandShift = $shift->getDemandShift();
                if ($demandShift && $demandShift->getFacility()) {
                    $trackedTime->facilityName = $demandShift->getFacility()->getTitle();
                }
            }
        }

        return $trackedTimes;
    }

}
