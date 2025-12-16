<?php
namespace App\Controller\ApiController;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


class EmployeeStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(int $employeeId, Request $request): JsonResponse
    {
        $employee = $this->entityManager->find(Employee::class, $employeeId);

        if (!$employee) {
            return $this->json(['error' => 'Angestellter nicht gefunden'], 404);
        }

        $user = $employee->getUser();

        if($user->isActive()){
            $user->setActive(false);
        }else{
            $user->setActive(true);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'active' => $user->isActive()]);
    }
}
