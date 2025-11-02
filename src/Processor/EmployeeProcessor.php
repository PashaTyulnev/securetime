<?php

namespace App\Processor;

use App\Entity\Employee;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Repository\EmployeeRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EmployeeProcessor
{

    public function __construct(readonly EmployeeRepository     $employeeRepository,
                                readonly EntityManagerInterface $entityManager,
                                readonly DepartmentRepository   $departmentRepository,
                                readonly SerializerInterface $serializer,
                                readonly ValidatorInterface     $validator)
    {
    }

    /**
     * @throws \Exception
     */
    public function createEmployee(mixed $newEmployeeData): void
    {

        $username = $newEmployeeData['username'];
        $number = $newEmployeeData['number'];
        $department = $newEmployeeData['department'];
        $firstName = $newEmployeeData['firstName'];
        $lastName = $newEmployeeData['lastName'];
        $birthDate = new DateTime($newEmployeeData['birthDate']);
        $phone = $newEmployeeData['phone'];
        $email = $newEmployeeData['email'];

        $department = $this->departmentRepository->findOneBy(['shortTitle' => $department]);

        $newEmployee = new Employee();
        $newEmployee->setFirstName($firstName);
        $newEmployee->setLastName($lastName);
        $newEmployee->setNumber($number);
        $newEmployee->setDepartment($department);
        $newEmployee->setPhone($phone);
        $newEmployee->setBirthDate($birthDate);

        $this->entityManager->persist($newEmployee);

        $newUser = new User();
        $newUser->setUsername($username);
        $newUser->setActive(true);
        $newUser->setEmail($email);
        $newUser->setRoles(['ROLE_EMPLOYEE']);
        $newUser->setEmployee($newEmployee);

        $this->validate($newEmployee);
        $this->validate($newUser);

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();


    }

    private function validate($entity): void
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                throw new ValidatorException($error->getMessage());
            }
        }
    }

    public function deleteEmployee(int $employeeId): void
    {
        $employee = $this->employeeRepository->find($employeeId);
        $user = $employee ? $employee->getUser() : null;

        if ($user) {
            $this->entityManager->remove($user);
        }

        if ($employee) {
            $this->entityManager->remove($employee);
        }

        $this->entityManager->flush();
    }
    /**
     * @throws Exception
    */
    public function updateEmployee(int $employeeId, mixed $employeeData): void
    {
        $employee = $this->employeeRepository->find($employeeId);

        if (!$employee) {
            throw new Exception('Angestellter nicht gefunden');
        }

        // Если департамент передан — найти объект и установить вручную
        if (isset($employeeData['department'])) {
            $department = $this->departmentRepository->findOneBy(['shortTitle' => $employeeData['department']]);
            if (!$department) {
                throw new Exception('Abteilung nicht gefunden');
            }
            $employee->setDepartment($department);
            unset($employeeData['department']); // чтобы не перезаписать ниже
        }

        // Обновление email пользователя
        if (isset($employeeData['email'])) {
            $user = $employee->getUser();
            if ($user) {
                $user->setEmail($employeeData['email']);
            }
            unset($employeeData['email']);
        }

        // überträgt automatisch alle Felder aus $employeeData auf das bestehende Objekt
        $this->serializer->denormalize(
            $employeeData,
            Employee::class,
            null,
            [AbstractNormalizer::OBJECT_TO_POPULATE => $employee]
        );

        $this->validate($employee);
        $this->validate($employee->getUser());
        $this->entityManager->persist($employee);
        $this->entityManager->flush();


    }

}
