<?php

namespace App\Controller\Employee;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class EmployeeController extends AbstractController
{

    public function __construct(readonly DepartmentRepository $departmentRepository)
    {
    }

    #[Route(path: '/employees', name: 'app_employees')]
    public function loadIndexPage(Security $security): Response
    {

        return $this->render('pages/employee/employee_index.html.twig', [

        ]);
    }

}
