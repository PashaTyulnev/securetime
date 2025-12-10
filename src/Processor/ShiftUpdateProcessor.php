<?php

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShiftUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        readonly ShiftRepository $shiftRepository
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $shiftId = $uriVariables['id'];
        $existingShift = $this->shiftRepository->find($shiftId);
        $existingShift->setIsOnCall($data->isOnCall());

        $this->entityManager->persist($existingShift);
        $this->entityManager->flush();

        //serialisiere shift ausgeben
        return $existingShift;
    }
}
