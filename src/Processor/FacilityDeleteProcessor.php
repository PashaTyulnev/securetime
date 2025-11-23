<?php
namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FacilityDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        private readonly ValidatorInterface $validator
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                $errors->get(0)->getMessage()
            );
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
