<?php

namespace App\Controller;

use App\Repository\ShiftRepository;
use App\Service\ShiftService;
use App\Service\TimeTracker\TimeTrackerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        readonly ShiftService $shiftService,
        readonly TimeTrackerService $timeTrackerService
    ) {
    }

    #[Route(path: '/', name: 'app_dashboard')]
    public function index(Security $security): \Symfony\Component\HttpFoundation\Response
    {
//        //prüfe ob user google token nicht null ist
//        $user = $security->getUser();
//        $googleAuthenticatorToken = $user->getGoogleAuthenticatorSecret();
//
//        // Prüfen, ob der Nutzer 2FA abgeschlossen hat
//        if ($googleAuthenticatorToken === null) {
//            return $this->redirectToRoute('app_register_google_auth');
//        }

        $user = $security->getUser();

        $todayWorkingInformation =
            $this->shiftService->getDashboardWorkingInfo($user, $this->timeTrackerService);

        $upcomingUserShifts = $this->shiftService->findUpcomingShiftsForUser($user, 7);

        return $this->render('dashboard.html.twig', [
            'todayWorkingInformation' => $todayWorkingInformation,
            'upcomingUserShifts' => $upcomingUserShifts,
        ]);
    }

}
