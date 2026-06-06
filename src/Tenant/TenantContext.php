<?php

namespace App\Tenant;

use App\Entity\Admin\Tenant;
use Symfony\Component\HttpFoundation\RequestStack;

class TenantContext
{
    private const ATTR = '_tenant';

    private ?Tenant $cliTenant = null;

    public function __construct(private readonly RequestStack $requestStack) {}

    public function setCurrentTenant(Tenant $tenant): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(self::ATTR, $tenant);
    }

    /** Setzt den Tenant direkt für CLI-Commands (kein HTTP-Request vorhanden). */
    public function setCliTenant(?Tenant $tenant): void
    {
        $this->cliTenant = $tenant;
    }

    public function getCurrentTenant(): ?Tenant
    {
        return $this->cliTenant
            ?? $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTR);
    }

    public function hasTenant(): bool
    {
        return $this->getCurrentTenant() !== null;
    }
}
