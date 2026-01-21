<?php

namespace App\Controller;

use App\Repository\ShiftRepository;
use App\Service\ShiftService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        readonly ShiftService $shiftService
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
        $todayShifts = $this->shiftService->findShiftsForToday();

        $isPlannedToday = false;

        if ($user) {
            foreach ($todayShifts as $shift) {
                $employee = $shift->getEmployee();
                if ($employee && $employee->getUser() && $employee->getUser()->getId() === $user->getId()) {
                    $isPlannedToday = true;
                    break;
                }
            }
        }

        return $this->render('empty.html.twig', [
            'todayShifts' => $todayShifts,
            'isPlannedToday' => $isPlannedToday,
        ]);
    }

}
