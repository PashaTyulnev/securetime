<?php

namespace App\Doctrine\Middleware;

use App\Tenant\TenantContext;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;

class TenantAwareDriver extends AbstractDriverMiddleware
{
    private readonly DsnParser $dsnParser;

    public function __construct(Driver $driver, private readonly TenantContext $tenantContext)
    {
        parent::__construct($driver);
        $this->dsnParser = new DsnParser(ConnectionFactory::DEFAULT_SCHEME_MAP);
    }

    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        $tenant = $this->tenantContext->getCurrentTenant();

        if ($tenant !== null && $tenant->getDatabaseUrl() !== null) {
            $tenantParams = $this->dsnParser->parse($tenant->getDatabaseUrl());
            $params = array_merge($params, $tenantParams);
        }

        return parent::connect($params);
    }
}
