<?php

namespace App\Service;

use App\Repository\CompanyRepository;
use Symfony\Component\Serializer\SerializerInterface;

class CompanyService
{

    public function __construct(readonly CompanyRepository $companyRepository, readonly SerializerInterface $serializer)
    {

    }
    public function getAllCompanies()
    {
        $companies = $this->companyRepository->findAll();

        $companies = $this->serializer->serialize(
            $companies,
            'jsonld',
            ['groups' => ['company:read']]
        );

        return json_decode($companies, true);
    }
}
