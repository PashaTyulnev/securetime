<?php

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Contact;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ContactProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Stelle sicher, dass $data eine Contact-Instanz ist
        if ($data instanceof Contact) {
            // Konvertiere leere Strings zu null für company
            if ($data->getCompany() ->getId() === null) {
                $data->setCompany(null);
            }

            // Konvertiere leere Strings zu null für facility
            if ($data->getFacility()->getId() === null) {
                $data->setFacility(null);
            }
        }

        // Leite an den Standard-Processor weiter
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
