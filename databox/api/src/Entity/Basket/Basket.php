<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\ESBundle\Indexer\ESIndexableInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\BasketInput;
use App\Api\Model\Output\BasketOutput;
use App\Api\Provider\BasketCollectionProvider;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
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
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new GetCollection(security: 'is_granted("IS_AUTHENTICATED_FULLY", object)'),
        new Post(securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'),
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
    final public const GROUP_READ = 'basket:read';
    final public const GROUP_LIST = 'basket:index';
    final public const GROUP_WRITE = 'basket:w';

    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\ManyToMany(targetEntity: BasketAsset::class)]
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
}
