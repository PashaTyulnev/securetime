<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\FacilityPositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FacilityPositionRepository::class)]
#[ApiResource]
class FacilityPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['facility:read','demandShift:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['facility:read','facility:write','demandShift:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['facility:read','facility:write','demandShift:read'])]
    private ?string $note = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    private ?Facility $facility = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['facility:read','facility:write','demandShift:read'])]
    private ?string $shortName = null;

    /**
     * @var Collection<int, DemandShift>
     */
    #[ORM\ManyToMany(targetEntity: DemandShift::class, mappedBy: 'requiredPositions')]
    private Collection $demandShifts;

    public function __construct()
    {
        $this->demandShifts = new ArrayCollection();
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName;

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
            $demandShift->addRequiredPosition($this);
        }

        return $this;
    }

    public function removeDemandShift(DemandShift $demandShift): static
    {
        if ($this->demandShifts->removeElement($demandShift)) {
            $demandShift->removeRequiredPosition($this);
        }

        return $this;
    }


}
