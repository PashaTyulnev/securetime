<?php

namespace App\Controller\PartnerCompany;

use App\Repository\PartnerCompanyRepository;
use App\Service\PartnerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/components/partner-company')]
class PartnerCompanyComponentController extends AbstractController
{

    public function __construct(
        readonly SerializerInterface $serializer,
        readonly PartnerService $partnerService,
        readonly PartnerCompanyRepository $partnerCompanyRepository
    ) {
    }

    #[Route(path: '/list', name: 'all_partner_company_list_component', methods: ['GET'])]
    public function getPartnerCompanyListComponent(): Response
    {
        $partnerCompanies = $this->partnerService->getAllPartners();
        return $this->render('pages/partner_company/partner_company_list.html.twig', [
            'partnerCompanies' => $partnerCompanies
        ]);
    }

    #[Route('/new', name: 'new_partner_company_component', methods: ['GET'])]
    public function getNewPartnerCompanyModalComponent(): Response
    {
        return $this->render('pages/partner_company/partner_company_modal.html.twig', [
        ]);
    }

    #[Route(path: '/edit/{partnerCompanyId}', name: 'edit_partner_company_component', methods: ['GET'])]
    public function getEditPartnerCompanyModalComponent(int $partnerCompanyId): Response
    {
        $partnerCompany = $this->partnerCompanyRepository->find($partnerCompanyId);

        $partnerCompany = $this->serializer->serialize(
            $partnerCompany,
            'jsonld',
            ['groups' => ['partner:read']]
        );

        $partnerCompany = json_decode($partnerCompany, true);

        return $this->render('pages/partner_company/partner_company_modal.html.twig', [
            'partnerCompany' => $partnerCompany
        ]);
    }
}

