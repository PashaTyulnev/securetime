<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\PartnerCompanyRepository;
use App\Repository\RoleRepository;
use Symfony\Component\Serializer\SerializerInterface;


class RoleService
{
    public function __construct(readonly RoleRepository $roleRepository,
                                readonly SerializerInterface $serializer)
    {
    }

    public function getAllRoles()
    {
        $roles = $this->roleRepository->findAll();

        $roles = $this->serializer->serialize(
            $roles,
            'jsonld',
            ['groups' => ['role:read']]
        );

        return json_decode($roles, true);
    }
}
