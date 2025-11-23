<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Processor\FacilityDeleteProcessor;
use App\Repository\FacilityRepository;
use App\Validator\NoDemandShiftsConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[NoDemandShiftsConstraint]
#[ORM\Entity(repositoryClass: FacilityRepository::class)]
#[Assert\Expression(
    expression: "!this.getDateTo() || !this.getDateFrom() || this.getDateTo() > this.getDateFrom()",
    message: "Das Ende-Datum muss nach dem Start-Datum liegen."
)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete(processor: FacilityDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['facility:read']],
    denormalizationContext: ['groups' => ['facility:write']]
)]
class Facility
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['contact:read', 'facility:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['contact:read', 'facility:read', 'facility:write'])]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Groups(['contact:read', 'facility:read', 'facility:write'])]
    private ?string $shortTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['contact:read', 'facility:read', 'facility:write'])]
    private ?string $description = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['facility:read', 'facility:write'])]
    private ?Address $address = null;

    #[ORM\ManyToOne(inversedBy: 'facility')]
    #[Groups(['facility:read', 'facility:write'])]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['facility:read', 'facility:write'])]
    private ?string $approach = null;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'facility')]
    private Collection $contacts;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['facility:read', 'facility:write'])]
    private ?\DateTime $dateFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['facility:read', 'facility:write'])]
    private ?\DateTime $dateTo = null;

    /**
     * @var Collection<int, Shift>
     */
    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'facility')]
    private Collection $shifts;

    /**
     * @var Collection<int, DemandShift>
     */
    #[ORM\OneToMany(targetEntity: DemandShift::class, mappedBy: 'facility')]
    #[Groups(['facility:read'])]
    private Collection $demandShifts;

    /**
     * @var Collection<int, FacilityPosition>
     */
    #[ORM\OneToMany(targetEntity: FacilityPosition::class, mappedBy: 'facility', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['facility:read','demandShift:read'])]
    private Collection $positions;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->shifts = new ArrayCollection();
        $this->demandShifts = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getShortTitle(): ?string
    {
        return $this->shortTitle;
    }

    public function setShortTitle(string $shortTitle): static
    {
        $this->shortTitle = $shortTitle;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getApproach(): ?string
    {
        return $this->approach;
    }

    public function setApproach(?string $approach): static
    {
        $this->approach = $approach;

        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setFacility($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getFacility() === $this) {
                $contact->setFacility(null);
            }
        }

        return $this;
    }

    public function getDateFrom(): ?\DateTime
    {
        return $this->dateFrom;
    }


    public function getDateTo(): ?\DateTime
    {
        return $this->dateTo;
    }

    public function setDateFrom(?\DateTime $dateFrom): static
    {
        // Leere Strings werden zu null
        if ($dateFrom === '' || $dateFrom === null) {
            $this->dateFrom = null;
        } else {
            $this->dateFrom = $dateFrom;
        }

        return $this;
    }

    public function setDateTo(?\DateTime $dateTo): static
    {
        // Leere Strings werden zu null
        if ($dateTo === '' || $dateTo === null) {
            $this->dateTo = null;
        } else {
            $this->dateTo = $dateTo;
        }

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
            $shift->setFacility($this);
        }

        return $this;
    }

    public function removeShift(Shift $shift): static
    {
        if ($this->shifts->removeElement($shift)) {
            // set the owning side to null (unless already changed)
            if ($shift->getFacility() === $this) {
                $shift->setFacility(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DemandShift>
     */
    public function getDemandShifts(): Collection
    {
        return $this->demandShifts;
    }

    public function addDemandShift(DemandShift $demandShift): static
    {
        if (!$this->demandShifts->contains($demandShift)) {
            $this->demandShifts->add($demandShift);
            $demandShift->setFacility($this);
        }

        return $this;
    }

    public function removeDemandShift(DemandShift $demandShift): static
    {
        if ($this->demandShifts->removeElement($demandShift)) {
            // set the owning side to null (unless already changed)
            if ($demandShift->getFacility() === $this) {
                $demandShift->setFacility(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FacilityPosition>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(FacilityPosition $position): static
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setFacility($this);
        }

        return $this;
    }

    public function removePosition(FacilityPosition $position): static
    {
        if ($this->positions->removeElement($position)) {
            // set the owning side to null (unless already changed)
            if ($position->getFacility() === $this) {
                $position->setFacility(null);
            }
        }

        return $this;
    }
}
