<?php
namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Contract;
use App\Entity\Employee;
use App\Entity\User;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class EmployeeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        readonly ContractRepository $contractRepository,
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

            //get data from request
            $rawData = json_decode($request->getContent(), true);

            $contractData = $rawData['contracts'] ?? null;

            $this->handleContractData($employee, $contractData);

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

        if (isset($requestData['contracts'])) {
            $this->handleContractData($data, $requestData['contracts']);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function handleContractData(&$employee, mixed $contractData): void
    {

        $type = $contractData['type'] ?? null;
        $maxMonthHours = $contractData['maxMonthHours'] ?? null;

        $existingContract = $this->contractRepository->findOneBy(['employee' => $employee]);

        if($existingContract) {
            // Update existing contract
            if ($type !== null) {
                $existingContract->setType($type);
            }
            if ($maxMonthHours !== null) {
                $existingContract->setMaxMonthHours($maxMonthHours);
            }
        } else {
            // Create new contract
            $newContract = new Contract();
            if ($type !== null) {
                $newContract->setType($type);
            }
            if ($maxMonthHours !== null) {
                $newContract->setMaxMonthHours($maxMonthHours);
            }

            $newContract->setEmployee($employee);
            $this->entityManager->persist($newContract);

        }

    }
}
