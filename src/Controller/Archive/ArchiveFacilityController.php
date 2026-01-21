<?php

namespace App\Controller\Archive;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArchiveFacilityController extends AbstractController
{
    #[Route(path: '/archive/facilities', name: 'app_archive_facilities')]
    public function loadIndexPage(): Response
    {
        return $this->render('pages/archive/archive_facility_index.html.twig', [

        ]);
    }
}

