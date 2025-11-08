<?php

namespace App\Provider;

use App\Entity\Employee;
use App\Repository\CompanyRepository;
use Symfony\Component\Serializer\SerializerInterface;

class CompanyProvider
{
    public function __construct(public CompanyRepository $companyRepository, private SerializerInterface $serializer)
    {
    }

    public function getAllCompanies(): array
    {
        $allCompanies = $this->companyRepository->findAll();

        //to json
        $companyArray = [];
        foreach ($allCompanies as $company) {
            $companyArray[] = $this->formanCompanyToArray($company);
        }

        return $companyArray;
    }

    private function formatEmployeeToArray(Employee $employee): array
    {
        return $this->serializer->normalize($employee, null, [
            'groups' => ['employee:read']]);

    }
}
