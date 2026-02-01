<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin-Funktion: Als Mitarbeiter anmelden (Impersonation).
 */
#[IsGranted('ROLE_ADMIN')]
class SwitchUserController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository
    ) {
    }

    #[Route('/switch-user', name: 'app_switch_user', methods: ['GET'])]
    public function index(): Response
    {
        $employees = $this->employeeRepository->findEmployeesWithUser(true);

        return $this->render('pages/switch_user/index.html.twig', [
            'employees' => $employees,
        ]);
    }
}
