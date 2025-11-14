<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\EmployeeRepository;
use App\Processor\EmployeeProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor:EmployeeProcessor::class),
        new Put(processor:EmployeeProcessor::class),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['employee:read']],
    denormalizationContext: ['groups' => ['employee:write']]
)]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['employee:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read', 'employee:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read', 'employee:write'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['employee:read', 'employee:write'])]
    private ?\DateTime $birthDate = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read', 'employee:write'])]
    private ?string $phone = null;

    #[ORM\OneToOne(mappedBy: 'employee', cascade: ['persist', 'remove'])]
    #[Groups(['employee:read', 'employee:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['employee:read', 'employee:write'])]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'employees')]
    #[Groups(['employee:read','employee:write'])]
    private ?Department $department = null;

    /**
     * @var Collection<int, Shift>
     */
    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'Employee')]
    private Collection $shifts;

    #[ORM\ManyToOne(inversedBy: 'employees')]
    #[Groups(['employee:read', 'employee:write'])]
    private ?Qualification $qualification = null;

    public function __construct()
    {
        $this->shifts = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getBirthDate(): ?\DateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTime $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setEmployee(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getEmployee() !== $this) {
            $user->setEmployee($this);
        }

        $this->user = $user;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;

        return $this;
    }

    /**
     * @return Collection<int, Shift>
     */
    public function getShifts(): Collection
    {
        return $this->shifts;
    }

    public function addShift(Shift $shift): static
    {
        if (!$this->shifts->contains($shift)) {
            $this->shifts->add($shift);
            $shift->setEmployee($this);
        }

        return $this;
    }

    public function removeShift(Shift $shift): static
    {
        if ($this->shifts->removeElement($shift)) {
            // set the owning side to null (unless already changed)
            if ($shift->getEmployee() === $this) {
                $shift->setEmployee(null);
            }
        }

        return $this;
    }

    public function getQualification(): ?Qualification
    {
        return $this->qualification;
    }

    public function setQualification(?Qualification $qualification): static
    {
        $this->qualification = $qualification;

        return $this;
    }


}
