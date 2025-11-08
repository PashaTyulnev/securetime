<?php

namespace App\Entity;

use App\Repository\AddressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read', 'company:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $street = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $zip = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $country = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?float $altitude = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): static
    {
        $this->altitude = $altitude;

        return $this;
    }
}
