<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\QualificationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: QualificationRepository::class)]
#[ApiResource]
class Qualification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['employee:read','facility:read','demandShift:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read', 'employee:write','demandShift:read','facility:read'])]
    private ?string $title = null;

    #[ORM\Column]
    private ?int $rank = null;

    /**
     * @var Collection<int, Employee>
     */
    #[ORM\OneToMany(targetEntity: Employee::class, mappedBy: 'qualification')]
    private Collection $employees;

    /**
     * @var Collection<int, DemandShift>
     */
    #[ORM\OneToMany(targetEntity: DemandShift::class, mappedBy: 'requiredQualification')]
    private Collection $demandShifts;

    public function __construct()
    {
        $this->employees = new ArrayCollection();
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

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(int $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function getEmployees(): Collection
    {
        return $this->employees;
    }

    public function addEmployee(Employee $employee): static
    {
        if (!$this->employees->contains($employee)) {
            $this->employees->add($employee);
            $employee->setQualification($this);
        }

        return $this;
    }

    public function removeEmployee(Employee $employee): static
    {
        if ($this->employees->removeElement($employee)) {
            // set the owning side to null (unless already changed)
            if ($employee->getQualification() === $this) {
                $employee->setQualification(null);
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
            $demandShift->setRequiredQualification($this);
        }

        return $this;
    }

    public function removeDemandShift(DemandShift $demandShift): static
    {
        if ($this->demandShifts->removeElement($demandShift)) {
            // set the owning side to null (unless already changed)
            if ($demandShift->getRequiredQualification() === $this) {
                $demandShift->setRequiredQualification(null);
            }
        }

        return $this;
    }
}
