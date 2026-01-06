<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\PartnerCompanyRepository;
use Symfony\Component\Serializer\SerializerInterface;

class PartnerService
{

    public function __construct(readonly PartnerCompanyRepository $partnerCompanyRepository,
                                readonly SerializerInterface $serializer,
                                readonly EmployeeRepository $employeeRepository)
    {
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
