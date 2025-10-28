<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class SecurityController extends AbstractController
{

    public function __construct(readonly GoogleAuthenticatorInterface $googleAuthenticator)
    {

    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/generate2fa', name: 'generate_2fa')]
    public function generate2fa(GoogleAuthenticatorInterface $googleAuthenticator, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('login');
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

        return $this->render('security/2fa_form.html.twig', [
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }
}
