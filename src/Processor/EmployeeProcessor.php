<?php
namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
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
        if (!$request) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $requestData = json_decode($request->getContent(), true);

        // BEI PUT: Employee aus DB laden und Werte übertragen
        if ($operation instanceof Put && !empty($uriVariables['id'])) {
            $employee = $this->entityManager->find(Employee::class, $uriVariables['id']);

            if (!$employee) {
                throw new \RuntimeException('Employee not found');
            }

            // Übertrage alle Werte vom deserialisierten $data zum DB-Employee
            $employee->setFirstName($data->getFirstName());
            $employee->setLastName($data->getLastName());
            $employee->setPhone($data->getPhone());
            $employee->setNumber($data->getNumber());
            $employee->setBirthDate($data->getBirthDate());
            $employee->setDepartment($data->getDepartment());
            $employee->setQualification($data->getQualification());

            $data = $employee;
        }

        // --- HANDLE USER ---
        if (isset($requestData['user'])) {
            $userData = $requestData['user'];

            if (!empty($userData['id'])) {
                $user = $this->entityManager->find(User::class, $userData['id']);

                if ($user) {
                    if (isset($userData['username'])) {
                        $user->setUsername($userData['username']);
                    }
                    if (isset($userData['email'])) {
                        $user->setEmail($userData['email']);
                    }
                    if (isset($userData['active'])) {
                        $user->setActive($userData['active']);
                    }

                    $data->setUser($user);
                }
            } else {
                $user = new User();
                if (isset($userData['username'])) {
                    $user->setUsername($userData['username']);
                }
                if (isset($userData['email'])) {
                    $user->setEmail($userData['email']);
                }
                $user->setActive($userData['active'] ?? true);
                $user->setRoles(['ROLE_EMPLOYEE']);

                $this->entityManager->persist($user);
                $data->setUser($user);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
