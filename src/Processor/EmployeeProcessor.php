<?php
namespace App\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Employee;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class EmployeeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Employee) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $requestData = json_decode($request->getContent(), true);

            if ($operation instanceof Post && $data->getUser() === null) {
                if (!empty($requestData['username']) && !empty($requestData['email'])) {
                    $user = new User();
                    $user->setUsername($requestData['username']);
                    $user->setEmail($requestData['email']);
                    $user->setActive(true);
                    $user->setRoles(['ROLE_EMPLOYEE']);
                    $user->setEmployee($data);

                    $data->setUser($user);
                    $this->entityManager->persist($user);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
