<?php

namespace App\Service;

use App\Repository\DepartmentRepository;
use App\Repository\FacilityRepository;
use Symfony\Component\Serializer\SerializerInterface;

class DepartmentService
{
    public function __construct(readonly DepartmentRepository $departmentRepository, readonly SerializerInterface $serializer)
    {

    }
    public function getAllDepartments()
    {
        $departments = $this->departmentRepository->findAllSortedByPosition();

        $departments = $this->serializer->serialize(
            $departments,
            'jsonld',
            ['groups' => ['employee:read']]
        );

        return json_decode($departments, true);
    }
}
