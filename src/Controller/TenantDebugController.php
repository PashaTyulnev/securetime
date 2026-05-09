<?php

namespace App\Controller;

use App\Tenant\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TenantDebugController extends AbstractController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    #[Route('/tenant-debug', name: 'tenant_debug', methods: ['GET'])]
    public function debug(Request $request): JsonResponse
    {
        $host      = $request->getHost();
        $tenant    = $this->tenantContext->getCurrentTenant();

        return new JsonResponse([
            'host'          => $host,
            'tenant_found'  => $tenant !== null,
            'tenant_name'   => $tenant?->getName(),
            'database_name' => $tenant?->getDatabaseName(),
            'is_active'     => $tenant?->isActive(),
        ]);
    }
}
