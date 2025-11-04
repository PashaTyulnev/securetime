<?php

namespace App\Controller\Facility;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FacilityController extends AbstractController
{

    public function __construct()
    {
    }

    #[Route(path: '/facility', name: 'app_facility')]
    public function loadIndexPage(Security $security): Response
    {

        return $this->render('facility/facility_index.html.twig', [

        ]);
    }
}
