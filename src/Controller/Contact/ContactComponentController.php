<?php

namespace App\Controller\Contact;

use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Service\CompanyService;
use App\Service\ContactService;
use App\Service\FacilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/components/contact')]
class ContactComponentController extends AbstractController
{

    public function __construct(readonly SerializerInterface $serializer,
                                readonly ContactService $contactService,
                                readonly CompanyService      $companyService,
                                readonly ContactRepository   $contactRepository)
    {
    }

    #[Route(path: '/list', name: 'all_contact_list_component', methods: ['GET'])]
    public function getContactListComponent(): Response
    {

        $contacts = $this->contactRepository->findAll();

        $contacts = $this->serializer->serialize(
            $contacts,
            'jsonld',
            ['groups' => ['contact:read']]
        );


        $contacts = json_decode($contacts, true);

        return $this->render('contact/contact_list.html.twig', [
            'contacts' => $contacts
        ]);
    }

    #[Route('/new', name: 'new_contact_component', methods: ['GET'])]
    public function getNewContactModalComponent(): Response
    {

        $companies =  $this->contactService->getAllCompanies();
        $facilities =  $this->contactService->getAllFacilities();


        return $this->render('contact/contact_modal.html.twig', [
            'companies' => $companies,
            'facilities' => $facilities
        ]);
    }

    #[Route(path: '/edit/{contactId}', name: 'edit_contact_component', methods: ['GET'])]
    public function getEditCompanyModalComponent(int $contactId): Response
    {
        $contact = $this->contactService->getContactById($contactId);
        $companies =  $this->contactService->getAllCompanies();
        $facilities =  $this->contactService->getAllFacilities();

        return $this->render('contact/contact_modal.html.twig', [
            'contact' => $contact,
            'companies' => $companies,
            'facilities' => $facilities
        ]);
    }
}
