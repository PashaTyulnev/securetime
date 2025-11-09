<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Processor\ContactProcessor;
use App\Repository\ContactRepository;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['contact:read']],
    denormalizationContext: ['groups' => ['contact:write']]
)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read','contact:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'contact:read','contact:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'contact:read','contact:write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'contact:read','contact:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'contact:read','contact:write'])]
    private ?string $email = null;

    #[ORM\ManyToOne(inversedBy: 'contacts')]
    #[Groups(['contact:read', 'contact:write'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(inversedBy: 'contacts')]
    #[Groups(['contact:read', 'contact:write','contact:write'])]
    private ?Facility $facility = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['company:read', 'contact:read','contact:write'])]
    private ?string $jobrole = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getFacility(): ?Facility
    {
        return $this->facility;
    }

    public function setFacility(?Facility $facility): static
    {
        $this->facility = $facility;

        return $this;
    }

    public function getJobrole(): ?string
    {
        return $this->jobrole;
    }

    public function setJobrole(?string $jobrole): static
    {
        $this->jobrole = $jobrole;

        return $this;
    }
}
