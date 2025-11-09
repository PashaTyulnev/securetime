<?php

namespace App\Service;

use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\FacilityRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ContactService
{
    public function __construct(readonly FacilityRepository $facilityRepository,
                                readonly ContactRepository $contactRepository,
                                readonly CompanyRepository $companyRepository,
                                readonly SerializerInterface $serializer)
    {

    }

    public function getAllCompanies()
    {
        $companies = $this->companyRepository->findAll();

        $companies = $this->serializer->serialize(
            $companies,
            'jsonld',
            ['groups' => ['contact:read']]
        );

        return json_decode($companies, true);
    }

    public function getAllFacilities()
    {
        $facilities = $this->facilityRepository->findAll();

        $facilities = $this->serializer->serialize(
            $facilities,
            'jsonld',
            ['groups' => ['contact:read']]
        );

        return json_decode($facilities, true);
    }

    public function getContactById(int $contactId)
    {
        $contact = $this->contactRepository->find($contactId);

        $contact = $this->serializer->serialize(
            $contact,
            'jsonld',
            ['groups' => ['contact:read']]
        );

        return json_decode($contact, true);

    }
}
