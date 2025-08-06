<?php

declare(strict_types=1);

namespace App\Entity\Shop;

use App\Entity\User\ShopUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_customer_csc')]
class CustomerCsc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopUser::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ShopUser $user = null;

    #[ORM\Column(type: 'json')]
    private array $cscs = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?ShopUser
    {
        return $this->user;
    }

    public function setUser(?ShopUser $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCscs(): array
    {
        return $this->cscs;
    }

    public function setCscs(array $cscs): self
    {
        $this->cscs = $cscs;
        return $this;
    }

    public function addCsc(string $reference, array $cscData): self
    {
        $this->cscs[$reference] = $cscData;
        return $this;
    }

    public function removeCsc(string $reference): self
    {
        unset($this->cscs[$reference]);
        return $this;
    }

    public function hasCsc(string $reference): bool
    {
        return isset($this->cscs[$reference]);
    }

    public function getCsc(string $reference): ?array
    {
        return $this->cscs[$reference] ?? null;
    }
}
