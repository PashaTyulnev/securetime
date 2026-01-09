<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\PartnerCompanyRepository;
use App\Repository\RoleRepository;
use Symfony\Component\Serializer\SerializerInterface;


class RoleService
{
    public function __construct(readonly RoleRepository $roleRepository,
                                readonly PartnerService $partnerService,
                                readonly SerializerInterface $serializer)
    {
    }

    public function getAllRoles()
    {
        $loggedInPartner = $this->partnerService->getLoggedInPartner();

        if($loggedInPartner){
            //nur rollen ROLE_EMPLOYEE und ROLE_PARTNER_ADMIN
            $roles = $this->roleRepository->findBy(['name' => ['ROLE_EMPLOYEE', 'ROLE_PARTNER_ADMIN']]);
        }
        else{
            //alle rollen
            $roles = $this->roleRepository->findAll();
        }

        $roles = $this->serializer->serialize(
            $roles,
            'jsonld',
            ['groups' => ['role:read']]
        );

        return json_decode($roles, true);
    }
}
