<?php

namespace App\Controller\Facility;

use App\Service\CompanyService;
use App\Service\FacilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/facility')]
class FacilityComponentController extends AbstractController
{

    public function __construct(readonly FacilityService $facilityService, readonly CompanyService $companyService)
    {

    }

    #[Route(path: '/list', name: 'all_facility_list_component', methods: ['GET'])]
    public function getFacilityListComponent(): Response
    {
        //call function from other controller
        $allFacilities = $this->facilityService->getAllFacilities();
        return $this->render('pages/facility/facility_list.html.twig', [
            'facilities' => $allFacilities
        ]);
    }

    #[Route(path: '/new', name: 'new_facility_component', methods: ['GET'])]
    public function getNewFacilityModalComponent(): Response
    {

        $allCompanies = $this->companyService->getAllCompanies();
        return $this->render('pages/facility/facility_modal.html.twig', [
            'companies' => $allCompanies
        ]);
    }

    #[Route(path: '/position', name: 'position_component', methods: ['GET'])]
    public function getPositionComponent(): Response
    {

        return $this->render('pages/facility/facility_position_single.html.twig', [
        ]);
    }

    #[Route(path: '/edit/{facilityId}', name: 'edit_facility_component', methods: ['GET'])]
    public function getEditFacilityModalComponent(int $facilityId): Response
    {
        $facility = $this->facilityService->getFacilityById($facilityId);

        $allCompanies = $this->companyService->getAllCompanies();
        return $this->render('pages/facility/facility_modal.html.twig', [
            'companies' => $allCompanies,
            'facility' => $facility
        ]);
    }
}
