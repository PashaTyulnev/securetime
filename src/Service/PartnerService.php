<?php

namespace App\Service;

use App\Entity\PartnerCompany;
use App\Repository\EmployeeRepository;
use App\Repository\PartnerCompanyRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

class PartnerService
{

    public function __construct(readonly PartnerCompanyRepository $partnerCompanyRepository,
                                readonly SerializerInterface $serializer,
                                readonly Security $security,
                                readonly EmployeeRepository $employeeRepository)
    {
    }

    public function getLoggedInPartner(): ?PartnerCompany
    {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser) {
            return null;
        }
        
        $currentEmployee = $currentUser->getEmployee();
        if (!$currentEmployee) {
            return null;
        }
        
        return $currentEmployee->getPartnerCompany();
    }

    public function getAllPartners()
    {
        $partners = $this->partnerCompanyRepository->findAll();

        $partners = $this->serializer->serialize(
            $partners,
            'jsonld',
            ['groups' => ['partner:read']]
        );

        return json_decode($partners, true);
    }
}
