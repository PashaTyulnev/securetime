<?php
namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class DemandShiftDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $persistProcessor,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        try {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        } catch (ForeignKeyConstraintViolationException $e) {
            throw new BadRequestHttpException(
                'Diese Schichtdefinition kann nicht gelöscht werden, da sie bereits verwendet wird.'
            );
        }
    }
}
