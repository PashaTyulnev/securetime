<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ShiftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ApiResource]
class Shift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateTimeFrom = null;

    #[ORM\Column]
    private ?\DateTime $dateTimeTo = null;

    #[ORM\ManyToOne(inversedBy: 'shifts')]
    private ?Facility $facility = null;

    #[ORM\ManyToOne(inversedBy: 'shifts')]
    private ?Employee $Employee = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateTimeFrom(): ?\DateTime
    {
        return $this->dateTimeFrom;
    }

    public function setDateTimeFrom(\DateTime $dateTimeFrom): static
    {
        $this->dateTimeFrom = $dateTimeFrom;

        return $this;
    }

    public function getDateTimeTo(): ?\DateTime
    {
        return $this->dateTimeTo;
    }

    public function setDateTimeTo(\DateTime $dateTimeTo): static
    {
        $this->dateTimeTo = $dateTimeTo;

        return $this;
    }

    public function getFacility(): ?Facility
    {
        return $this->facility;
    }

    public function setFacility(?Facility $facility): static
    {
        $this->facility = $facility;

        return $this;
    }

    public function getEmployee(): ?Employee
    {
        return $this->Employee;
    }

    public function setEmployee(?Employee $Employee): static
    {
        $this->Employee = $Employee;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
