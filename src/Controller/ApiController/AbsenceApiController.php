<?php

namespace App\Controller\ApiController;

use App\Entity\User;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Exception\AbsenceException;
use App\Repository\AbsenceRepository;
use App\Repository\EmployeeRepository;
use App\Service\AbsenceService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AbsenceApiController extends AbstractController
{
    public function __construct(
        readonly AbsenceService $absenceService,
        readonly AbsenceRepository $absenceRepository,
        readonly EmployeeRepository $employeeRepository,
    ) {}

    #[Route('/api/absences', name: 'api_absence_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $employeeId = $request->query->get('employeeId') !== null
            ? (int) $request->query->get('employeeId')
            : null;

        $status = null;
        if ($request->query->has('status')) {
            $status = AbsenceStatus::tryFrom($request->query->get('status'));
            if ($status === null) {
                return new JsonResponse(['message' => 'Ungültiger Status. Erlaubt: pending, approved, rejected.'], 400);
            }
        }

        $type = null;
        if ($request->query->has('type')) {
            $type = AbsenceType::tryFrom($request->query->get('type'));
            if ($type === null) {
                return new JsonResponse(['message' => 'Ungültiger Typ. Erlaubt: vacation, sick, unavailable.'], 400);
            }
        }

        $startDate = null;
        $endDate   = null;

        if ($request->query->has('startDate')) {
            $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $request->query->get('startDate'));
            if (!$startDate) {
                return new JsonResponse(['message' => 'Ungültiges startDate-Format. Erwartet: YYYY-MM-DD.'], 400);
            }
        }

        if ($request->query->has('endDate')) {
            $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $request->query->get('endDate'));
            if (!$endDate) {
                return new JsonResponse(['message' => 'Ungültiges endDate-Format. Erwartet: YYYY-MM-DD.'], 400);
            }
        }

        $absences = $this->absenceRepository->findFiltered($employeeId, $status, $type, $startDate, $endDate);

        $result = array_map(fn($absence) => [
            'id'           => $absence->getId(),
            'employee'     => [
                'id'   => $absence->getEmployee()?->getId(),
                'name' => $absence->getEmployee()?->getFirstName() . ' ' . $absence->getEmployee()?->getLastName(),
            ],
            'type'         => $absence->getType()->value,
            'status'       => $absence->getStatus()->value,
            'startDate'    => $absence->getStartDate()?->format('Y-m-d'),
            'endDate'      => $absence->getEndDate()?->format('Y-m-d'),
            'reason'       => $absence->getReason(),
            'adminComment' => $absence->getAdminComment(),
        ], $absences);

        return new JsonResponse($result);
    }

    #[Route('/api/absences/calendar', name: 'api_absence_calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        $startDate = null;
        $endDate   = null;

        if ($request->query->has('startDate')) {
            $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $request->query->get('startDate'));
            if (!$startDate) {
                return new JsonResponse(['message' => 'Ungültiges startDate-Format. Erwartet: YYYY-MM-DD.'], 400);
            }
        }

        if ($request->query->has('endDate')) {
            $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $request->query->get('endDate'));
            if (!$endDate) {
                return new JsonResponse(['message' => 'Ungültiges endDate-Format. Erwartet: YYYY-MM-DD.'], 400);
            }
        }

        $absences = $this->absenceRepository->findFiltered(null, null, null, $startDate, $endDate);

        $typeLabels = AbsenceType::choices();

        $result = array_map(fn($absence) => [
            'id'     => $absence->getId(),
            'title'  => $absence->getEmployee()?->getFirstName() . ' ' . $absence->getEmployee()?->getLastName()
                        . ' - ' . ($typeLabels[$absence->getType()->value] ?? $absence->getType()->value),
            'start'  => $absence->getStartDate()?->format('Y-m-d'),
            'end'    => $absence->getEndDate()?->format('Y-m-d'),
            'type'   => $absence->getType()->value,
            'status' => $absence->getStatus()->value,
        ], $absences);

        return new JsonResponse($result);
    }

    #[Route('/api/absences', name: 'api_absence_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $employeeId = isset($data['employeeId']) ? (int) $data['employeeId'] : null;
        $typeValue  = $data['type'] ?? null;
        $startRaw   = $data['startDate'] ?? null;
        $endRaw     = $data['endDate'] ?? null;
        $reason     = $data['reason'] ?? null;

        if (!$employeeId || !$typeValue || !$startRaw || !$endRaw) {
            return new JsonResponse(['message' => 'employeeId, type, startDate und endDate sind erforderlich.'], 400);
        }

        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return new JsonResponse(['message' => 'Mitarbeiter nicht gefunden.'], 404);
        }

        try {
            $type      = AbsenceType::from($typeValue);
            $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startRaw);
            $endDate   = DateTimeImmutable::createFromFormat('Y-m-d', $endRaw);
        } catch (\ValueError) {
            return new JsonResponse(['message' => 'Ungültiger Abwesenheitstyp. Erlaubt: vacation, sick, unavailable.'], 400);
        }

        if (!$startDate || !$endDate) {
            return new JsonResponse(['message' => 'Ungültiges Datumsformat. Erwartet: YYYY-MM-DD.'], 400);
        }

        try {
            $absence = $this->absenceService->createAbsence($employee, $type, $startDate, $endDate, $reason);
        } catch (AbsenceException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'id'         => $absence->getId(),
            'employeeId' => $absence->getEmployee()->getId(),
            'type'       => $absence->getType()->value,
            'status'     => $absence->getStatus()->value,
            'startDate'  => $absence->getStartDate()?->format('Y-m-d'),
            'endDate'    => $absence->getEndDate()?->format('Y-m-d'),
            'reason'     => $absence->getReason(),
        ], 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/absences/{id}/approve', name: 'api_absence_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $absence = $this->absenceRepository->find($id);
        if (!$absence) {
            return new JsonResponse(['message' => 'Abwesenheit nicht gefunden.'], 404);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data        = json_decode($request->getContent(), true);
        $adminComment = $data['adminComment'] ?? null;

        try {
            $this->absenceService->approveAbsence($absence, $currentUser, $adminComment);
        } catch (AbsenceException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'id'           => $absence->getId(),
            'status'       => $absence->getStatus()->value,
            'approvedBy'   => $absence->getApprovedBy()?->getId(),
            'approvedAt'   => $absence->getApprovedAt()?->format('Y-m-d H:i:s'),
            'adminComment' => $absence->getAdminComment(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/absences/{id}/reject', name: 'api_absence_reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        $absence = $this->absenceRepository->find($id);
        if (!$absence) {
            return new JsonResponse(['message' => 'Abwesenheit nicht gefunden.'], 404);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data        = json_decode($request->getContent(), true);
        $adminComment = $data['adminComment'] ?? null;

        try {
            $this->absenceService->rejectAbsence($absence, $currentUser, $adminComment);
        } catch (AbsenceException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'id'           => $absence->getId(),
            'status'       => $absence->getStatus()->value,
            'rejectedBy'   => $absence->getRejectedBy()?->getId(),
            'rejectedAt'   => $absence->getRejectedAt()?->format('Y-m-d H:i:s'),
            'adminComment' => $absence->getAdminComment(),
        ]);
    }
}
