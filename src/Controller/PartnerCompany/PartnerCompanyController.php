<?php

namespace App\Controller\PartnerCompany;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PartnerCompanyController extends AbstractController
{

    #[Route(path: '/partner-company', name: 'app_partner_company')]
    public function loadIndexPage(): Response
    {
        return $this->render('pages/partner_company/partner_company_index.html.twig', [
        ]);
    }
}

