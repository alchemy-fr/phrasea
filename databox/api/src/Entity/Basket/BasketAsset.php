<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Api\Provider\BasketAssetCollectionProvider;
use App\Entity\Core\Asset;
use App\Entity\Traits\AssetAnnotationsTrait;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/baskets/{id}/assets',
    operations: [
        new GetCollection(),
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'basket', fromClass: Basket::class),
    ],
    normalizationContext: [
        'groups' => [
            Asset::GROUP_LIST,
            self::GROUP_LIST,
        ],
    ],
    order: ['position' => 'ASC'],
    provider: BasketAssetCollectionProvider::class,
)]
class BasketAsset extends AbstractUuidEntity implements WithOwnerIdInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use AssetAnnotationsTrait;

    public const string GROUP_LIST = 'basket-asset:list';

    #[ORM\ManyToOne(targetEntity: Basket::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Basket $basket = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_LIST])]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_LIST])]
    private ?array $context = [];

    #[ORM\Column(type: Types::BIGINT, nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private ?string $position = '0';

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

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    public function getPosition(): ?int
    {
        if (null === $this->position) {
            return null;
        }

        return (int) $this->position;
    }

    public function setPosition(int|string $position): void
    {
        $this->position = (string) $position;
    }
}
