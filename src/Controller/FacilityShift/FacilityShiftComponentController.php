<?php

namespace App\Controller\FacilityShift;

use App\Service\FacilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/facility-shift')]
class FacilityShiftComponentController extends AbstractController
{

    public function __construct(readonly FacilityService $facilityService)
    {

    }

    #[Route(path: '/new-shift-component', name: 'app_new_facility_shift_component', methods: ['GET'])]
    public function loadNewFacilityShiftComponent(Request $request): Response
    {
        $facilityUri = $request->query->get('facilityUri');

        return $this->render('pages/facility_shift/facility_single_shift.html.twig', [
            'facility' => [
                '@id' => $facilityUri
            ]
        ]);
    }

    #[Route(path: '/fetch-shift-component', name: 'app_facility_shift_component', methods: ['POST'])]
    public function loadFacilityShiftComponent(Request $request): Response
    {

        $newShiftData = json_decode($request->getContent(), true);

        return $this->render('pages/facility_shift/facility_single_shift.html.twig', [
            'facility' => [
                '@id' => $newShiftData['facility']
            ],
            'demandShift' => $newShiftData
        ]);
    }
}
