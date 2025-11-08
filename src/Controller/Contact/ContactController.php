<?php

namespace App\Controller\Contact;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{

    #[Route(path: '/contact', name: 'app_contact')]
    public function loadIndexPage(Security $security): Response
    {

        return $this->render('contact/contact_index.html.twig', [

        ]);
    }

}
