<?php

namespace App\Controller\Schedule;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScheduleController extends AbstractController
{
    #[Route(path: '/schedule', name: 'app_schedule')]
    public function loadIndexPage(Security $security): Response
    {

        return $this->render('pages/schedule/schedule_week/schedule_week_index.html.twig', [

        ]);
    }
}
