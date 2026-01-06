<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RoleRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ApiResource]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role:read','employee:read','user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['role:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['role:read'])]
    private ?string $displayName = null;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'roleEntities')]
    private Collection $users;

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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }
}
