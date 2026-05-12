<?php

namespace App\Doctrine\Middleware;

use App\Tenant\TenantContext;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

class TenantConnectionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function wrap(Driver $driver): Driver
    {
        return new TenantAwareDriver($driver, $this->tenantContext);
    }
}
