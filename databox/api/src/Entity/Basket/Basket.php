<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ESBundle\Indexer\ESIndexableInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AddToBasketInput;
use App\Api\Model\Input\BasketInput;
use App\Api\Model\Input\RemoveFromBasketInput;
use App\Api\Model\Output\BasketOutput;
use App\Api\Processor\AddToBasketProcessor;
use App\Api\Processor\RemoveFromBasketProcessor;
use App\Api\Provider\BasketCollectionProvider;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Transformer\HighlightableModelInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'basket',
    operations: [
        new GetCollection(security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")'),
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
        ),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'
        ),
        new Post(
            uriTemplate: '/baskets/default/assets',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            input: AddToBasketInput::class,
            name: 'add_to_default_basket',
            processor: AddToBasketProcessor::class,
        ),
        new Post(
            uriTemplate: '/baskets/{id}/assets',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: AddToBasketInput::class,
            name: 'add_to_basket',
            processor: AddToBasketProcessor::class,
        ),
        new Post(
            uriTemplate: '/baskets/{id}/remove',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: RemoveFromBasketInput::class,
            name: 'remove_from_basket',
            processor: RemoveFromBasketProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    input: BasketInput::class,
    output: BasketOutput::class,
    provider: BasketCollectionProvider::class,
)]
#[ORM\Entity(repositoryClass: BasketRepository::class)]
class Basket extends AbstractUuidEntity implements WithOwnerIdInterface, AclObjectInterface, ESIndexableInterface, HighlightableModelInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const GROUP_READ = 'basket:read';
    final public const GROUP_LIST = 'basket:index';
    final public const GROUP_WRITE = 'basket:w';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'basket', targetEntity: BasketAsset::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Collection $assets = null;

    private ?array $highlights = null;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->assets = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return BasketAsset[]|Collection
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function setAssets(Collection $assets): void
    {
        $this->assets = $assets;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId();
    }

    public function isObjectIndexable(): bool
    {
        return true;
    }

    public function setElasticHighlights(array $highlights)
    {
        $this->highlights = $highlights;

        return $this;
    }

    public function getElasticHighlights(): ?array
    {
        return $this->highlights;
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? 'Basket - '.$this->getId();
    }
}
