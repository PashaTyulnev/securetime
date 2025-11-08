<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[UniqueEntity(fields: ['company'], message: 'Diese Firma ist bereits angelegt.')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['company:read']],
    denormalizationContext: ['groups' => ['company:write']]
)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $number = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $company = null;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'company')]
    #[Groups(['company:read', 'company:write'])]
    private Collection $contacts;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    #[Assert\Valid]
    private ?Address $address = null;

    /**
     * @var Collection<int, Facility>
     */
    #[ORM\OneToMany(targetEntity: Facility::class, mappedBy: 'company')]
    private Collection $facility;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoLink = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->facility = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): static
    {
        $this->company = $company;

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
            $contact->setCompany($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getCompany() === $this) {
                $contact->setCompany(null);
            }
        }

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection<int, Facility>
     */
    public function getFacility(): Collection
    {
        return $this->facility;
    }

    public function addFacility(Facility $facility): static
    {
        if (!$this->facility->contains($facility)) {
            $this->facility->add($facility);
            $facility->setCompany($this);
        }

        return $this;
    }

    public function removeFacility(Facility $facility): static
    {
        if ($this->facility->removeElement($facility)) {
            // set the owning side to null (unless already changed)
            if ($facility->getCompany() === $this) {
                $facility->setCompany(null);
            }
        }

        return $this;
    }

    public function getLogoLink(): ?string
    {
        return $this->logoLink;
    }

    public function setLogoLink(?string $logoLink): static
    {
        $this->logoLink = $logoLink;

        return $this;
    }
}
