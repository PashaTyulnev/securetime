<?php

namespace App\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Employee;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Exception\ValidatorException;

readonly class EmployeeStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface     $persistProcessor,
        private DepartmentRepository   $departmentRepository,
        private RequestStack           $requestStack,
        private EntityManagerInterface $entityManager,
        private EmployeeRepository     $employeeRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Employee) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Beim Update das bestehende Employee-Objekt aus der Datenbank laden
        if ($operation instanceof Put && isset($uriVariables['id'])) {
            $existingEmployee = $this->employeeRepository->find($uriVariables['id']);
            if ($existingEmployee === null) {
                throw new ValidatorException('Mitarbeiter nicht gefunden.');
            }
            
            // Bestehende Daten auf das geladene Objekt anwenden
            $existingEmployee->setFirstName($data->getFirstName());
            $existingEmployee->setLastName($data->getLastName());
            $existingEmployee->setBirthDate($data->getBirthDate());
            $existingEmployee->setPhone($data->getPhone());
            $existingEmployee->setNumber($data->getNumber());
            
            // Das geladene Objekt verwenden
            $data = $existingEmployee;
        }

        // Rohe Daten aus dem Request extrahieren
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            $requestData = json_decode($request->getContent(), true);

            // Wenn das Department als String (shortTitle) gesendet wurde, finden wir es in der Datenbank
            if (isset($requestData['department']) && is_string($requestData['department'])) {
                $department = $this->departmentRepository->findOneBy([
                    'shortTitle' => $requestData['department']
                ]);

                if ($department !== null) {
                    $data->setDepartment($department);
                } else {
                    // Department nicht gefunden - auf null setzen
                    $data->setDepartment(null);
                }
            } elseif (isset($requestData['department']) && is_array($requestData['department'])) {
                // Wenn das Department als Objekt gesendet wurde, versuchen wir es anhand des shortTitle zu finden
                if (isset($requestData['department']['shortTitle'])) {
                    $department = $this->departmentRepository->findOneBy([
                        'shortTitle' => $requestData['department']['shortTitle']
                    ]);

                    if ($department !== null) {
                        $data->setDepartment($department);
                    } else {
                        $data->setDepartment(null);
                    }
                } else {
                    $data->setDepartment(null);
                }
            }

            // User erstellen oder aktualisieren
            if ($requestData !== null) {
                if ($operation instanceof Post) {
                    // User erstellen, wenn ein neuer Employee erstellt wird und User-Daten vorhanden sind
                    if ($data->getUser() === null && isset($requestData['username']) && isset($requestData['email'])) {
                        $user = new User();
                        $user->setUsername($requestData['username']);
                        $user->setEmail($requestData['email']);
                        $user->setActive(true);
                        $user->setRoles(['ROLE_EMPLOYEE']);
                        $user->setEmployee($data);

                        $this->entityManager->persist($user);
                        $data->setUser($user);
                    }
                } elseif ($operation instanceof Put) {
                    // User aktualisieren, wenn ein Employee bearbeitet wird
                    $user = $data->getUser();

                    if ($user !== null) {
                        if (isset($requestData['username'])) {
                            $user->setUsername($requestData['username']);
                        }
                        if (isset($requestData['email'])) {
                            $user->setEmail($requestData['email']);
                        }
                        $this->entityManager->persist($user);
                    }
                }
            }
        }

        // Prüfen, ob das Department bereits als Objekt gesetzt ist (falls API Platform es deserialisiert hat)
        if ($data->getDepartment() !== null) {
            $department = $data->getDepartment();

            // Wenn das Department keine ID hat, bedeutet das, dass es ein neues Objekt ist
            // Wir müssen es durch ein bestehendes ersetzen
            if ($department->getId() === null) {
                // Versuchen, das Department anhand des shortTitle zu finden
                if ($department->getShortTitle() !== null) {
                    $existingDepartment = $this->departmentRepository->findOneBy([
                        'shortTitle' => $department->getShortTitle()
                    ]);

                    if ($existingDepartment !== null) {
                        $data->setDepartment($existingDepartment);
                    } else {
                        // Department nicht gefunden - auf null setzen
                        $data->setDepartment(null);
                    }
                } else {
                    // Kein shortTitle - auf null setzen
                    $data->setDepartment(null);
                }
            }
        }

        // Validierung der Personalnummer: Prüfen, ob sie bereits von einem anderen Employee verwendet wird
        if ($data->getNumber() !== null) {
            $existingEmployee = $this->employeeRepository->findOneBy(['number' => $data->getNumber()]);

            // Aktuelle Employee-ID bestimmen (beim Update aus uriVariables, sonst aus dem Objekt)
            $currentEmployeeId = null;
            if ($operation instanceof Put && isset($uriVariables['id'])) {
                $currentEmployeeId = $uriVariables['id'];
            } elseif ($data->getId() !== null) {
                $currentEmployeeId = $data->getId();
            }

            // Wenn ein Employee mit dieser Personalnummer existiert und es nicht der aktuelle Employee ist
            if ($existingEmployee !== null && $existingEmployee->getId() != $currentEmployeeId) {
                throw new ValidatorException('Diese Personalnummer ist bereits vergeben.');
            }
        }

        // Weiterverarbeitung durch den Standard-Processor
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

