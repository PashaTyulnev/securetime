<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{

    #[Route(path: '/', name: 'app_dashboard')]
    public function test()
    {

        return $this->render('empty.html.twig', [

        ]);
    }
}
