<?php

namespace App\Controller\Company;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanyController extends AbstractController
{

    public function __construct(readonly DepartmentRepository $departmentRepository)
    {
    }

    #[Route(path: '/company', name: 'app_company')]
    public function loadIndexPage(Security $security): Response
    {

        return $this->render('company/company_index.html.twig', [

        ]);
    }

}
