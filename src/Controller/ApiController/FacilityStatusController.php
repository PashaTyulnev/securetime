<?php

namespace App\Controller\ApiController;

use App\Entity\Facility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FacilityStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(int $facilityId, Request $request): JsonResponse
    {
        $facility = $this->entityManager->find(Facility::class, $facilityId);

        if (!$facility) {
            return $this->json(['error' => 'Objekt nicht gefunden'], 404);
        }

        $facility->setActive(!$facility->isActive());

        $this->entityManager->persist($facility);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'active' => $facility->isActive()]);
    }
}

