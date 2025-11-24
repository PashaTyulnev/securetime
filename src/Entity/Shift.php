<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ShiftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['shift:read']],
    denormalizationContext: ['groups' => ['shift:write']])
]
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


}
