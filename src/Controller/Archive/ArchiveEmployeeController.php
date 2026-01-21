<?php

namespace App\Controller\Archive;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArchiveEmployeeController extends AbstractController
{
    #[Route(path: '/archive/employees', name: 'app_archive_employees')]
    public function loadIndexPage(): Response
    {
        return $this->render('pages/archive/archive_employee_index.html.twig', [

        ]);
    }
}
