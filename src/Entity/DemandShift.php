<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Processor\DemandShiftDeleteProcessor;
use App\Processor\EmployeeProcessor;
use App\Repository\DemandShiftRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DemandShiftRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor:EmployeeProcessor::class),
        new Put(processor:EmployeeProcessor::class),
        new Delete(processor: DemandShiftDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['demandShift:read']],
    denormalizationContext: ['groups' => ['demandShift:write']]
)]
#[ApiFilter(DateFilter::class, properties: ['validFrom', 'validTo'])]
#[ApiFilter(SearchFilter::class, properties: ['facility' => 'exact'])]
class DemandShift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','shift:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'demandShifts')]
    #[Groups(['demandShift:read','demandShift:write','shift:read'])]
    private ?Facility $facility = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['facility:read','demandShift:read','demandShift:write','shift:read'])]
    private ?\DateTime $validFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['facility:read','demandShift:read','demandShift:write','shift:read'])]
    private ?\DateTime $validTo = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(['facility:read','demandShift:read','demandShift:write','shift:read'])]
    private ?\DateTime $timeFrom = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(['facility:read','demandShift:read','demandShift:write','shift:read'])]
    private ?\DateTime $timeTo = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write','shift:read'])]
    private ?int $amountEmployees = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onMonday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onTuesday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onWednesday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onThursday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onFriday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onSaturday = null;

    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?bool $onSunday = null;

    #[ORM\Column(length: 255)]
    #[Groups(['facility:read','demandShift:read','demandShift:write'])]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'demandShifts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['demandShift:read','demandShift:write','facility:read'])]
    private ?Qualification $requiredQualification = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['demandShift:read','demandShift:write','facility:read','shift:read'])]
    private ?string $color = null;

    /**
     * @var Collection<int, FacilityPosition>
     */
    #[ORM\ManyToMany(targetEntity: FacilityPosition::class, inversedBy: 'demandShifts')]
    #[Groups(['demandShift:read','demandShift:write','facility:read'])]
    private Collection $requiredPositions;

    /**
     * @var Collection<int, Shift>
     */
    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'demandShift')]
    private Collection $shifts;

    public function __construct()
    {
        $this->requiredPositions = new ArrayCollection();
        $this->shifts = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getValidFrom(): ?\DateTime
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTime $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTime
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTime $validTo): static
    {
        $this->validTo = $validTo;

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

    public function setTimeTo(\DateTime $timeTo): static
    {
        $this->timeTo = $timeTo;

        return $this;
    }


    public function getAmountEmployees(): ?int
    {
        return $this->amountEmployees;
    }

    public function setAmountEmployees(int $amountEmployees): static
    {
        $this->amountEmployees = $amountEmployees;

        return $this;
    }

    public function isOnMonday(): ?bool
    {
        return $this->onMonday;
    }

    public function setOnMonday(bool $onMonday): static
    {
        $this->onMonday = $onMonday;

        return $this;
    }

    public function isOnTuesday(): ?bool
    {
        return $this->onTuesday;
    }

    public function setOnTuesday(bool $onTuesday): static
    {
        $this->onTuesday = $onTuesday;

        return $this;
    }

    public function isOnWednesday(): ?bool
    {
        return $this->onWednesday;
    }

    public function setOnWednesday(bool $onWednesday): static
    {
        $this->onWednesday = $onWednesday;

        return $this;
    }

    public function isOnThursday(): ?bool
    {
        return $this->onThursday;
    }

    public function setOnThursday(bool $onThursday): static
    {
        $this->onThursday = $onThursday;

        return $this;
    }

    public function isOnFriday(): ?bool
    {
        return $this->onFriday;
    }

    public function setOnFriday(bool $onFriday): static
    {
        $this->onFriday = $onFriday;

        return $this;
    }

    public function isOnSaturday(): ?bool
    {
        return $this->onSaturday;
    }

    public function setOnSaturday(bool $onSaturday): static
    {
        $this->onSaturday = $onSaturday;

        return $this;
    }

    public function isOnSunday(): ?bool
    {
        return $this->onSunday;
    }

    public function setOnSunday(bool $onSunday): static
    {
        $this->onSunday = $onSunday;

        return $this;
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

    public function getRequiredQualification(): ?Qualification
    {
        return $this->requiredQualification;
    }

    public function setRequiredQualification(?Qualification $requiredQualification): static
    {
        $this->requiredQualification = $requiredQualification;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return Collection<int, FacilityPosition>
     */
    public function getRequiredPositions(): Collection
    {
        return $this->requiredPositions;
    }

    public function addRequiredPosition(FacilityPosition $requiredPosition): static
    {
        if (!$this->requiredPositions->contains($requiredPosition)) {
            $this->requiredPositions->add($requiredPosition);
            $requiredPosition->addDemandShift($this);
        }

        return $this;
    }

    public function removeRequiredPosition(FacilityPosition $requiredPosition): static
    {
        if ($this->requiredPositions->removeElement($requiredPosition)) {
            $requiredPosition->removeDemandShift($this);
        }

        return $this;
    }

    /**
     * @deprecated Use getRequiredPositions() instead
     * @return Collection<int, FacilityPosition>
     */
    public function getRequiredPosition(): Collection
    {
        return $this->requiredPositions;
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
            $shift->setDemandShift($this);
        }

        return $this;
    }

    public function removeShift(Shift $shift): static
    {
        if ($this->shifts->removeElement($shift)) {
            // set the owning side to null (unless already changed)
            if ($shift->getDemandShift() === $this) {
                $shift->setDemandShift(null);
            }
        }

        return $this;
    }

}
