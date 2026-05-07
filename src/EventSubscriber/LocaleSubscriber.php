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
        $allowedLocales = ['de', 'ru', 'ar'];

        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            $locale = $request->getSession()->get('_locale');

            if (!$locale) {
                $cookieLocale = $request->cookies->get('_locale');
                $locale = in_array($cookieLocale, $allowedLocales) ? $cookieLocale : $this->defaultLocale;
                $request->getSession()->set('_locale', $locale);
            }

            $request->setLocale($locale);
        }
    }
}
