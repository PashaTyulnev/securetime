<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'de')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        if (!$request->hasPreviousSession()) {
            return;
        }

        // Versuche Locale aus Session zu holen
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            // Versuche Locale aus Session zu holen, sonst Browser-Locale, sonst Default
            $sessionLocale = $request->getSession()->get('_locale');
            
            if ($sessionLocale) {
                $request->setLocale($sessionLocale);
            } else {
                // Versuche Browser-Locale zu erkennen
                $preferredLanguage = $request->getPreferredLanguage(['de', 'ru', 'ar']);
                
                if ($preferredLanguage) {
                    $request->setLocale($preferredLanguage);
                    $request->getSession()->set('_locale', $preferredLanguage);
                } else {
                    // Fallback auf Deutsch
                    $request->setLocale($this->defaultLocale);
                    $request->getSession()->set('_locale', $this->defaultLocale);
                }
            }
        }
    }
}
