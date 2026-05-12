<?php

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use App\Tenant\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DemoAutoLoginSubscriber implements EventSubscriberInterface
{
    private const DEMO_SUBDOMAIN = 'demo';
    private const DEMO_USERNAME  = 'admin';
    private const FIREWALL       = 'main';

    public function __construct(
        private readonly TenantContext        $tenantContext,
        private readonly UserRepository       $userRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // after TenantSubscriber (512) so tenant is already set
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // skip logout so the session clear actually takes effect
        if ($request->getPathInfo() === '/logout') {
            return;
        }

        $tenant = $this->tenantContext->getCurrentTenant();
        if ($tenant === null || $tenant->getSubdomain() !== self::DEMO_SUBDOMAIN) {
            return;
        }

        // already authenticated
        $existing = $this->tokenStorage->getToken();
        if ($existing?->getUser() !== null) {
            return;
        }

        $user = $this->userRepository->findOneBy(['username' => self::DEMO_USERNAME]);
        if ($user === null) {
            return;
        }

        $token = new UsernamePasswordToken($user, self::FIREWALL, $user->getRoles());
        $this->tokenStorage->setToken($token);

        // persist in session so subsequent requests stay authenticated
        $request->getSession()->set('_security_' . self::FIREWALL, serialize($token));
    }
}
