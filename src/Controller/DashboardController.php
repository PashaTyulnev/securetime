<?php

namespace App\Controller;

use App\Repository\ShiftRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ShiftRepository $shiftRepository
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

        $todayShifts = $this->shiftRepository->findShiftsForToday();

        return $this->render('empty.html.twig', [
            'todayShifts' => $todayShifts
        ]);
    }

}
