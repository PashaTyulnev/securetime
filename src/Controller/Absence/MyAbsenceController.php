<?php

namespace App\Controller\Absence;

use App\Entity\User;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Exception\AbsenceException;
use App\Repository\AbsenceRepository;
use App\Service\AbsenceService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MyAbsenceController extends AbstractController
{
    public function __construct(
        private readonly AbsenceService $absenceService,
        private readonly AbsenceRepository $absenceRepository,
    ) {}

    #[Route(path: '/my/absences', name: 'app_my_absences', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user     = $this->getUser();
        $employee = $user->getEmployee();

        if (!$employee) {
            return $this->render('pages/absence/my_absence_index.html.twig', [
                'noEmployee' => true,
                'absences'   => [],
            ]);
        }

        $absences = $this->absenceRepository->findFiltered(employeeId: $employee->getId());

        return $this->render('pages/absence/my_absence_index.html.twig', [
            'noEmployee' => false,
            'absences'   => $absences,
        ]);
    }

    #[Route(path: '/my/absences', name: 'app_my_absences_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        /** @var User $user */
        $user     = $this->getUser();
        $employee = $user->getEmployee();

        if (!$employee) {
            $this->addFlash('error', 'Kein Mitarbeiter-Profil gefunden.');
            return $this->redirectToRoute('app_my_absences');
        }

        $typeValue = $request->request->get('type', '');
        $startRaw  = $request->request->get('startDate', '');
        $endRaw    = $request->request->get('endDate', '');
        $reason    = trim($request->request->get('reason', '')) ?: null;

        $type = AbsenceType::tryFrom($typeValue);
        if (!$type) {
            $this->addFlash('error', 'Bitte einen gültigen Abwesenheitstyp auswählen.');
            return $this->redirectToRoute('app_my_absences');
        }

        $startDate = $startRaw ? DateTimeImmutable::createFromFormat('Y-m-d', $startRaw) : false;
        $endDate   = $endRaw   ? DateTimeImmutable::createFromFormat('Y-m-d', $endRaw)   : false;

        if (!$startDate || !$endDate) {
            $this->addFlash('error', 'Bitte gültige Datumswerte eingeben.');
            return $this->redirectToRoute('app_my_absences');
        }

        try {
            $this->absenceService->createAbsence($employee, $type, $startDate, $endDate, $reason);
            $this->addFlash('success', 'Dein Antrag wurde erfolgreich eingereicht.');
        } catch (AbsenceException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_my_absences');
    }
}
