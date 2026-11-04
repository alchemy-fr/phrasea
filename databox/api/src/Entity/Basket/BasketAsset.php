<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Api\Model\Input\Basket\BasketAssetInput;
use App\Api\Processor\UpdateBasketAssetProcessor;
use App\Api\Provider\BasketAssetCollectionProvider;
use App\Entity\Core\Asset;
use App\Entity\Traits\AssetAnnotationsTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use App\Model\BasketClip;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/baskets/{id}/assets',
    operations: [
        new GetCollection(),
        new Patch(
            uriTemplate: '/basket-assets/{id}',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            // `object` is the input DTO here: the basket is checked by the processor
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            input: BasketAssetInput::class,
            // The resource level provider lists the items of a basket: the id here is an item id
            provider: ItemProvider::class,
            processor: UpdateBasketAssetProcessor::class,
        ),
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

    /**
     * @return array{timeRange?: array{s: float, e: float}, crop?: array{x: float, y: float, w: float, h: float}}|null
     */
    #[Groups([self::GROUP_LIST])]
    public function getClip(): ?array
    {
        return $this->context['clip'] ?? null;
    }

    public function setClip(?array $clip): void
    {
        $context = $this->context ?? [];
        if (empty($clip)) {
            unset($context['clip']);
        } else {
            $context['clip'] = $clip;
        }
        $this->context = $context;
    }

    public function getBasketClip(): ?BasketClip
    {
        return BasketClip::fromArray($this->getClip());
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
