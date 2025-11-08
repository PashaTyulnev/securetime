<?php

namespace App\Controller\Company;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/components/company')]
class CompanyComponentController extends AbstractController
{

    public function __construct(readonly SerializerInterface $serializer)
    {
    }

    #[Route(path: '/list', name: 'all_company_list_component', methods: ['GET'])]
    public function getCompanyListComponent(CompanyRepository $companyRepository): Response
    {

        $companies = $companyRepository->findAll();

        // Nutze die gleiche Serialisierung wie API Platform
        $companies = $this->serializer->serialize(
            $companies,
            'jsonld',  // oder 'json'
            ['groups' => ['company:read']]
        );

        $companies = json_decode($companies, true);


        return $this->render('company/company_list.html.twig', [
            'companies' => $companies
        ]);
    }

    #[Route('/new', name: 'new_company_component', methods: ['GET'])]
    public function getNewCompanyModalComponent(): Response
    {
        return $this->render('company/company_modal.html.twig', [

        ]);
    }
}
