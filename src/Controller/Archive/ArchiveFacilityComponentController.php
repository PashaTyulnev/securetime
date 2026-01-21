<?php

namespace App\Controller\Archive;

use App\Service\FacilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/archive/facility')]
class ArchiveFacilityComponentController extends AbstractController
{
    public function __construct(readonly FacilityService $facilityService)
    {
    }

    #[Route(path: '/list', name: 'archive_facility_list_component', methods: ['GET'])]
    public function getArchiveFacilityListComponent(): Response
    {
        $inactiveFacilities = $this->facilityService->getInactiveFacilities();

        return $this->render('pages/archive/archive_facility_list.html.twig', [
            'facilities' => $inactiveFacilities
        ]);
    }
}

