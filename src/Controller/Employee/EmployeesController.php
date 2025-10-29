<?php

namespace App\Controller\Employee;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class EmployeesController extends AbstractController
{

    public function __construct()
    {
    }

    #[Route(path: '/employees', name: 'app_employees')]
    public function test(Security $security): Response
    {
        return $this->render('employee/employeeIndex.html.twig');
    }

}
