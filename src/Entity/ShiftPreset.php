<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ShiftPresetRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ShiftPresetRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['shiftPreset:read']],
    denormalizationContext: ['groups' => ['shiftPreset:write']]
)]
class ShiftPreset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['shiftPreset:read', 'shiftPreset:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['shiftPreset:read', 'shiftPreset:write'])]
    private ?string $color = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['shiftPreset:read', 'shiftPreset:write'])]
    private ?DateTime $timeFrom = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['shiftPreset:read', 'shiftPreset:write'])]
    private ?DateTime $timeTo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getTimeFrom(): ?\DateTime
    {
        return $this->timeFrom;
    }

    public function setTimeFrom(\DateTime $timeFrom): static
    {
        $this->timeFrom = $timeFrom;

        return $this;
    }

    public function getTimeTo(): ?\DateTime
    {
        return $this->timeTo;
    }

    public function setTimeTo(?\DateTime $timeTo): static
    {
        $this->timeTo = $timeTo;

        return $this;
    }
}
