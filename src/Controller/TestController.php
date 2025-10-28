<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{

    #[Route(path: '/', name: 'app_dashboard')]
    public function test(Security $security): \Symfony\Component\HttpFoundation\Response
    {
//        //prüfe ob user google token nicht null ist
//        $user = $security->getUser();
//        $googleAuthenticatorToken = $user->getGoogleAuthenticatorSecret();
//
//        // Prüfen, ob der Nutzer 2FA abgeschlossen hat
//        if ($googleAuthenticatorToken === null) {
//            return $this->redirectToRoute('app_register_google_auth');
//        }

        return $this->render('empty.html.twig');
    }

    #[Route(path: '/protected', name: 'app_protected')]
    public function testprotected()
    {

        return $this->render('testprotect.html.twig', [

        ]);
    }
}
