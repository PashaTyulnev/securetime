<?php

namespace App\Controller\Company;

use App\Repository\CompanyRepository;
use App\Service\CompanyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/components/company')]
class CompanyComponentController extends AbstractController
{

    public function __construct(readonly SerializerInterface $serializer,
                                readonly CompanyService      $companyService,
                                readonly CompanyRepository   $companyRepository)
    {
    }

    #[Route(path: '/list', name: 'all_company_list_component', methods: ['GET'])]
    public function getCompanyListComponent(): Response
    {

        $companies = $this->companyService->getAllCompanies();
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

    #[Route(path: '/edit/{companyId}', name: 'edit_company_component', methods: ['GET'])]
    public function getEditCompanyModalComponent(int $companyId): Response
    {
        $company = $this->companyRepository->find($companyId);

        $company = $this->serializer->serialize(
            $company,
            'jsonld',
            ['groups' => ['company:read']]
        );

        $company = json_decode($company, true);

        return $this->render('company/company_modal.html.twig', [
            'company' => $company
        ]);
    }
}
