<?php

namespace App\Controller\Absence;

use App\Enum\AbsenceStatus;
use App\Repository\AbsenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AbsenceController extends AbstractController
{
    public function __construct(private readonly AbsenceRepository $absenceRepository) {}

    #[Route(path: '/absences', name: 'app_absences')]
    public function loadIndexPage(Request $request): Response
    {
        $statusParam = $request->query->get('status', 'pending');
        $activeStatus = AbsenceStatus::tryFrom($statusParam) ?? AbsenceStatus::Pending;

        $absences = $this->absenceRepository->findFiltered(status: $activeStatus);

        return $this->render('pages/absence/absence_index.html.twig', [
            'absences'     => $absences,
            'activeStatus' => $activeStatus,
        ]);
    }
}
