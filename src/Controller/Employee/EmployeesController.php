<?php

namespace App\Controller\Employee;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class EmployeesController extends AbstractController
{

    public function __construct(readonly DepartmentRepository $departmentRepository)
    {
    }

    #[Route(path: '/employees', name: 'app_employees')]
    public function test(Security $security): Response
    {

        $departments = $this->departmentRepository->findAllSortedByPosition();

        return $this->render('employee/employeeIndex.html.twig', [
            'departments' => $departments,
        ]);
    }

}
