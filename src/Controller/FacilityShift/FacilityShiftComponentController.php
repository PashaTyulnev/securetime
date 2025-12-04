<?php

namespace App\Controller\FacilityShift;

use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ShiftService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/components/facility-shift')]
class FacilityShiftComponentController extends AbstractController
{

    public function __construct(readonly FacilityService $facilityService,
                                readonly ShiftService $shiftService,
                                readonly EmployeeService $employeeService)
    {

    }

    #[Route(path: '/new-shift-component', name: 'app_new_facility_shift_component', methods: ['GET'])]
    public function loadNewFacilityShiftComponent(Request $request): Response
    {
        $facilityUri = $request->query->get('facilityUri');

        // Extract facility ID from URI
        $facilityId = (int)basename($facilityUri);
        $facility = $this->facilityService->getFacilityById($facilityId);
        $qualifications = $this->employeeService->getAllQualifications();
        $shiftPresets = $this->shiftService->getAllDemandShiftPresets();

        return $this->render('pages/facility_shift/facility_single_shift.html.twig', [
            'facility' => $facility,
            'shiftPresets' => $shiftPresets,
            'qualifications' => $qualifications
        ]);
    }

    #[Route(path: '/fetch-shift-component', name: 'app_facility_shift_component', methods: ['POST'])]
    public function loadFacilityShiftComponent(Request $request): Response
    {

        $newShiftData = json_decode($request->getContent(), true);
        $qualifications = $this->employeeService->getAllQualifications();
        $shiftPresets = $this->shiftService->getAllDemandShiftPresets();

        $response = $this->render('pages/facility_shift/facility_single_shift.html.twig', [
            'facility' => $newShiftData['facility'],
            'demandShift' => $newShiftData,
            'shiftPresets' => $shiftPresets,
            'qualifications' => $qualifications
        ]);

        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route(path: '/fetch-facility-list-component', name: 'app_facility_list_component', methods: ['GET'])]
    public function fetchFacilityListComponent(): Response
    {

        $facilities = $this->facilityService->getAllFacilities();

        return $this->render('pages/facility_shift/facility_list.html.twig', [
            'facilities' => $facilities
        ]);
    }

    #[Route(path: '/fetch-demand-shifts-container-component', name: 'demand_shift_container_component', methods: ['GET'])]
    public function fetchDemandShiftsContainerComponent(Request $request): Response
    {
        $facilityId = $request->query->get('facilityId');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');

        $facility = $this->facilityService->getFacilityById($facilityId);
        $demandShifts = $this->shiftService->getDemandShiftsOfFacilityInDateRange($facilityId, $dateFrom, $dateTo);

        return $this->render('pages/facility_shift/demand_shifts_container.html.twig', [
            'facility' => $facility,
            'demandShifts' => $demandShifts,
            'shiftPresets' => $this->shiftService->getAllDemandShiftPresets(),
            'qualifications' => $this->employeeService->getAllQualifications()
        ]);
    }
}
