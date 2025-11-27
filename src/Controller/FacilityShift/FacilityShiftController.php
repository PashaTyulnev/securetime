<?php

namespace App\Controller\FacilityShift;

use App\Entity\Qualification;
use App\Service\EmployeeService;
use App\Service\FacilityService;
use App\Service\ShiftService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FacilityShiftController extends AbstractController
{
    public function __construct(readonly FacilityService $facilityService, readonly EmployeeService $employeeService, readonly ShiftService $shiftService)
    {

    }

    #[Route(path: '/facility-shift', name: 'app_facility_shift')]
    public function loadIndexPage(Security $security): Response
    {

        $allFacilities = $this->facilityService->getAllFacilities();
        $allQualifications = $this->employeeService->getAllQualifications();
        $shiftPresets = $this->shiftService->getAllShiftPresets();

        return $this->render('pages/facility_shift/facility_shift_index.html.twig', [
            'facilities' => $allFacilities,
            'shiftPresets' => $shiftPresets,
            'qualifications' => $allQualifications,
        ]);
    }
}
