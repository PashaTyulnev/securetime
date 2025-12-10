<?php

namespace App\Processor;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ShiftProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isOnCall = $data->getDemandShift()?->isOnCall();

        if ($isOnCall) {
            $data->setIsOnCall(true);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
