<?php
namespace App\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PartnerCompany;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class PartnerCompanyProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
        readonly EmployeeRepository $employeeRepository
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PartnerCompany) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($operation instanceof Delete) {
            // Prüfe ob der PartnerCompany Mitarbeitern zugewiesen ist
            $employees = $this->employeeRepository->findBy(['partnerCompany' => $data]);
            if (count($employees) > 0) {
                throw new \RuntimeException(
                    'Dieser Geschäftspartner kann nicht gelöscht werden, da ihm bereits ' . count($employees) . ' Mitarbeiter zugewiesen ' . (count($employees) === 1 ? 'ist' : 'sind') . '. Bitte entfernen Sie zuerst die Zuweisungen.'
                );
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

