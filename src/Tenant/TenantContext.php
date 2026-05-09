<?php

namespace App\Tenant;

use App\Entity\Admin\Tenant;
use Symfony\Component\HttpFoundation\RequestStack;

class TenantContext
{
    private const ATTR = '_tenant';

    public function __construct(private readonly RequestStack $requestStack) {}

    public function setCurrentTenant(Tenant $tenant): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(self::ATTR, $tenant);
    }

    public function getCurrentTenant(): ?Tenant
    {
        return $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTR);
    }

    public function hasTenant(): bool
    {
        return $this->getCurrentTenant() !== null;
    }
}
