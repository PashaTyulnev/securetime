<?php

namespace App\Controller\TimeTracker;

use App\Service\TimeTracker\TimeTrackerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[IsGranted('ROLE_USER')]
class TimeTrackerFrontendController extends AbstractController
{
    public function __construct(readonly Environment $twig, readonly TimeTrackerService $timeTrackerService)
    {

    }

    #[Route('/time_tracking_overview', name: 'app_time_tracking_index')]
    public function loadTimeTrackingIndex(): Response
    {
        $this->twig->addGlobal('currentPage', 'time_tracking_overview');
        return $this->render('pages/time_tracker/time_tracking_overview_index.html.twig');
    }

    #[Route('/time_tracking_overview/trackedTimesTable', name: 'adminTrackedTimesTable')]
    public function loadTimeTrackingOverview(Request $request): Response
    {
        $trackedTimes = json_decode($request->getContent());

        return $this->render('pages/time_tracker/tracked_times_table.html.twig', ['trackedTimes' => $trackedTimes]);
    }

    #[Route('/time_tracking_overview/lastUserTimeTrackingInformationTable', name: 'lastUserTimeTrackingInformationTable')]
    public function lastUserTimeTrackingInformationTable(Request $request): Response
    {
        $userInformationArrayElements = json_decode($request->getContent());

        return $this->render('pages/time_tracker/usersTable.html.twig', ['userInformation' => $userInformationArrayElements]);
    }

    #[Route('/time_tracking_overview/singleUserTotals', name: 'singleUserTotals')]
    public function singleUserTotals(Request $request): Response
    {
        $userTimeTrackingData = json_decode($request->getContent());

        return $this->render('pages/time_tracker/singleUserTotals.html.twig', ['timeTrackingSummary' => $userTimeTrackingData]);
    }

}
