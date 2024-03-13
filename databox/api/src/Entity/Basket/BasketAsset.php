<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class BasketAsset extends AbstractUuidEntity implements WithOwnerIdInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Basket::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Basket $basket = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = [];

    public function getBasket(): Basket
    {
        return $this->basket;
    }

    public function setBasket(Basket $basket): void
    {
        $this->basket = $basket;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getClip(): ?array
    {
        return $this->context['clip'] ?? null;
    }

    public function setClip(?array $clip): void
    {
        $this->context['clip'] = $clip;
    }
}
