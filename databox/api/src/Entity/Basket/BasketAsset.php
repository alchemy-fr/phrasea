<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/baskets/{id}/assets',
    operations: [
        new GetCollection()
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'basket', fromClass: Basket::class)
    ],
    normalizationContext: [
        'groups' => [
            Asset::GROUP_LIST,
            self::GROUP_LIST,
        ]
    ]
)]
class BasketAsset extends AbstractUuidEntity implements WithOwnerIdInterface
{
    private const GROUP_LIST = 'basket-asset:list';

    use OwnerIdTrait;
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Basket::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Basket $basket = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_LIST])]
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

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): void
    {
        $this->context = $context;
    }
}
