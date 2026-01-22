<?php

namespace App\Controller\Settings;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends AbstractController
{
    #[Route(path: '/settings', name: 'app_settings')]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        
        // Hole Locale aus Session oder verwende Request-Locale oder Default
        $currentLocale = $session->get('_locale');
        if (!$currentLocale) {
            $currentLocale = $request->getLocale() ?: 'de';
        }

        return $this->render('pages/settings/settings_index.html.twig', [
            'currentLocale' => $currentLocale,
        ]);
    }

    #[Route(path: '/settings/locale', name: 'app_settings_locale', methods: ['POST'])]
    public function changeLocale(Request $request): Response
    {
        $locale = $request->request->get('locale');
        
        // Validiere die Locale
        $allowedLocales = ['de', 'ru', 'ar'];
        if (!in_array($locale, $allowedLocales)) {
            $locale = 'de'; // Fallback auf Deutsch
        }

        $session = $request->getSession();
        $session->set('_locale', $locale);

        // Redirect zurück zu den Einstellungen
        return $this->redirectToRoute('app_settings');
    }
}
