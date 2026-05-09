<?php

namespace App\EventSubscriber;

use App\Entity\Admin\Tenant;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class TenantSubscriber implements EventSubscriberInterface
{
    /** Subdomains that belong to the platform itself, not to any tenant. */
    private const SYSTEM_SUBDOMAINS = ['app', 'www'];

    public function __construct(
        private readonly EntityManagerInterface $adminEntityManager,
        private readonly TenantContext $tenantContext,
        private readonly string $baseDomain,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 512]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $subdomain = $this->extractSubdomain($event->getRequest()->getHost());

        if ($subdomain === null) {
            return;
        }

        $tenant = $this->adminEntityManager
            ->getRepository(Tenant::class)
            ->findOneBy(['subdomain' => $subdomain]);

        if ($tenant === null) {
            throw new NotFoundHttpException(sprintf('Unbekannte Domain: %s', $event->getRequest()->getHost()));
        }

        if (!$tenant->isActive()) {
            throw new AccessDeniedHttpException(sprintf('Konto "%s" ist deaktiviert.', $subdomain));
        }

        $this->tenantContext->setCurrentTenant($tenant);
    }

    /**
     * Returns the subdomain for customer tenants, or null for system/unknown domains.
     *
     * Examples:
     *   demo.securetime.de   → "demo"
     *   app.securetime.de    → null  (system domain)
     *   securetime.de        → null  (root domain)
     *   localhost            → null  (local dev)
     */
    private function extractSubdomain(string $host): ?string
    {
        // strip port (e.g. localhost:8000)
        $host = strtolower(explode(':', $host)[0]);

        $suffix = '.' . $this->baseDomain;

        // host must end with .securetime.de to be relevant
        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        // root domain or system subdomain → not a tenant
        if ($subdomain === '' || in_array($subdomain, self::SYSTEM_SUBDOMAINS, true)) {
            return null;
        }

        return $subdomain;
    }
}
