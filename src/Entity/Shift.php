<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ShiftStatus;
use App\Processor\ShiftProcessor;
use App\Processor\ShiftUpdateProcessor;
use App\Repository\ShiftRepository;
use App\Validator\Constraint\EmployeeQualificationConstraint;
use App\Validator\Constraint\ShiftCustomRulesConstraint;
use App\Validator\Constraint\ShiftTimeRulesConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor:ShiftProcessor::class),
        new Put(
            denormalizationContext: ['groups' => ['shift:update']],
            validate: false,
            processor: ShiftUpdateProcessor::class
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['shift:read']],
    denormalizationContext: ['groups' => ['shift:write']])
]
#[EmployeeQualificationConstraint]
#[ShiftTimeRulesConstraint]
#[ShiftCustomRulesConstraint]
class Shift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['shift:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shifts')]
    #[Groups(['shift:write', 'shift:read'])]
    private ?Employee $employee = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?string $note = null;

    #[ORM\ManyToOne(inversedBy: 'shifts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?DemandShift $demandShift = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?\DateTime $date = null;

    #[ORM\Column(enumType: ShiftStatus::class)]
    #[Groups(['shift:read'])]
    private ?ShiftStatus $status = ShiftStatus::Pending;

    #[ORM\Column]
    #[Groups(['shift:write','shift:update'])]
    private ?bool $isOnCall = false;

    #[Groups(['shift:read'])]
    private int $onCall = 0;

    public function getOnCall(): ?bool
    {
        return (string)$this->isOnCall;
    }


    public function getId(): ?int
    {
        return $this->id;
    }


    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getDemandShift(): ?DemandShift
    {
        return $this->demandShift;
    }

    public function setDemandShift(?DemandShift $demandShift): static
    {
        $this->demandShift = $demandShift;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): ?ShiftStatus
    {
        return $this->status;
    }

    public function setStatus(ShiftStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isOnCall(): ?bool
    {
        return $this->isOnCall;
    }

    public function setIsOnCall(bool $isOnCall): static
    {
        $this->isOnCall = $isOnCall;

        return $this;
    }


}
