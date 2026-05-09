<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Facility;
use App\Enum\ShiftStatus;
use App\Processor\ShiftProcessor;
use App\Processor\ShiftUpdateProcessor;
use App\Repository\ShiftRepository;
use App\Validator\Constraint\EmployeeQualificationConstraint;
use App\Validator\Constraint\NoAbsenceConflictConstraint;
use App\Validator\Constraint\ShiftCustomRulesConstraint;
use App\Validator\Constraint\ShiftTimeRulesConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ORM\HasLifecycleCallbacks]
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
#[NoAbsenceConflictConstraint]
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
    #[Groups(['shift:write', 'shift:read'])]
    private ?DemandShift $demandShift = null;

    #[ORM\ManyToOne]
    #[Groups(['shift:write', 'shift:read'])]
    private ?Facility $customFacility = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?string $customName = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?\DateTime $customTimeFrom = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?\DateTime $customTimeTo = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['shift:write', 'shift:read'])]
    private ?string $customColor = null;

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

    /** Computed: date + demandShift timeFrom (not persisted). */
    public ?\DateTime $shiftBeginn = null;

    /** Computed: date + demandShift timeTo, next day if timeTo <= timeFrom (not persisted). */
    public ?\DateTime $shiftEnd = null;

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
        $this->updateShiftBeginnAndEnd();

        return $this;
    }

    public function getCustomFacility(): ?Facility { return $this->customFacility; }
    public function setCustomFacility(?Facility $customFacility): static { $this->customFacility = $customFacility; return $this; }

    public function getCustomName(): ?string { return $this->customName; }
    public function setCustomName(?string $customName): static { $this->customName = $customName; return $this; }

    public function getCustomTimeFrom(): ?\DateTime { return $this->customTimeFrom; }
    public function setCustomTimeFrom(?\DateTime $customTimeFrom): static
    {
        $this->customTimeFrom = $customTimeFrom;
        $this->updateShiftBeginnAndEnd();
        return $this;
    }

    public function getCustomTimeTo(): ?\DateTime { return $this->customTimeTo; }
    public function setCustomTimeTo(?\DateTime $customTimeTo): static
    {
        $this->customTimeTo = $customTimeTo;
        $this->updateShiftBeginnAndEnd();
        return $this;
    }

    public function getCustomColor(): ?string { return $this->customColor; }
    public function setCustomColor(?string $customColor): static { $this->customColor = $customColor; return $this; }

    /** Gibt die effektive Startzeit zurück (custom oder von DemandShift). */
    public function getEffectiveTimeFrom(): ?\DateTime
    {
        return $this->demandShift?->getTimeFrom() ?? $this->customTimeFrom;
    }

    /** Gibt die effektive Endzeit zurück (custom oder von DemandShift). */
    public function getEffectiveTimeTo(): ?\DateTime
    {
        return $this->demandShift?->getTimeTo() ?? $this->customTimeTo;
    }

    public function isCustomShift(): bool
    {
        return $this->demandShift === null;
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
        $this->updateShiftBeginnAndEnd();

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

    /**
     * Shift start: shift date + demandShift timeFrom.
     */
    public function getShiftBeginn(): ?\DateTime
    {
        return $this->shiftBeginn;
    }

    /**
     * Shift end: shift date + demandShift timeTo; if timeTo <= timeFrom, end is next day.
     */
    public function getShiftEnd(): ?\DateTime
    {
        return $this->shiftEnd;
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->updateShiftBeginnAndEnd();
    }

    private function updateShiftBeginnAndEnd(): void
    {
        if ($this->date === null) {
            $this->shiftBeginn = null;
            $this->shiftEnd = null;

            return;
        }
        $timeFrom = $this->getEffectiveTimeFrom();
        $timeTo   = $this->getEffectiveTimeTo();

        if ($timeFrom === null) {
            $this->shiftBeginn = null;
        } else {
            $this->shiftBeginn = clone $this->date;
            $this->shiftBeginn->setTime(
                (int) $timeFrom->format('H'),
                (int) $timeFrom->format('i'),
                (int) $timeFrom->format('s')
            );
        }

        if ($timeTo === null) {
            $this->shiftEnd = null;
        } else {
            $this->shiftEnd = clone $this->date;
            if ($timeFrom !== null && $timeTo->format('H:i:s') <= $timeFrom->format('H:i:s')) {
                $this->shiftEnd->modify('+1 day');
            }
            $this->shiftEnd->setTime(
                (int) $timeTo->format('H'),
                (int) $timeTo->format('i'),
                (int) $timeTo->format('s')
            );
        }
    }
}
