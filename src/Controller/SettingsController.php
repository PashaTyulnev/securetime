<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface as GoogleAuthInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends AbstractController
{

    #[Route(path: '/settings', name: 'app_settings')]
    public function index(): \Symfony\Component\HttpFoundation\Response
    {

        return $this->render('pages/settings/settings_index.html.twig', [

        ]);
    }

    #[Route('/registerGoogleAuth', name: 'app_register_google_auth')]
    public function generate2fa(GoogleAuthenticatorInterface $googleAuthenticator, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getGoogleAuthenticatorSecret() === null) {
            $secret = $googleAuthenticator->generateSecret();
            $user->setGoogleAuthenticatorSecret($secret);
            $em->flush();
        }

        $qrCodeContent = $googleAuthenticator->getQRContent($user);

        // QR-Code bauen (v5 Syntax!)
        $builder = new Builder(
            writer: new PngWriter(),
            data: $qrCodeContent,
            encoding: new Encoding('UTF-8'),
            size: 200,
            margin: 10,
        );

        $result = $builder->build();
        $qrCodeDataUri = $result->getDataUri();

        return $this->render('settings/googleAuthentication/google_auth_registration.html.twig', [
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    #[Route(path: '/settings/2fa/manual', name: 'app_2fa_manual', methods: ['GET'])]
    public function twoFaManualGet(): Response
    {
        // Render the same input used by scheb's default 2FA form
        return $this->render('security/google_auth_code_input.html.twig', [
            'authenticationError' => false,
            'form_action' => $this->generateUrl('app_2fa_manual_check'),
        ]);
    }

    #[Route(path: '/settings/2fa/manual', name: 'app_2fa_manual_check', methods: ['POST'])]
    public function twoFaManualPost(Request $request, GoogleAuthInterface $googleAuthenticator, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $code = $request->request->get('_auth_code');
        $isValid = $googleAuthenticator->checkCode($user, (string) $code);

        if (!$isValid) {
            return $this->render('security/google_auth_code_input.html.twig', [
                'authenticationError' => true,
                'form_action' => $this->generateUrl('app_2fa_manual_check'),
            ]);
        }

        // Code verified: keep the secret (already set) and send user to dashboard/home
        return $this->redirectToRoute('app_dashboard');
    }
}
