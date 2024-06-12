<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\CopyAssetInput;
use App\Api\Model\Input\MoveAssetInput;
use App\Api\Model\Input\MultipleAssetInput;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Api\Processor\AssetAttributeBatchUpdateProcessor;
use App\Api\Processor\CopyAssetProcessor;
use App\Api\Processor\MoveAssetProcessor;
use App\Api\Processor\MultipleAssetCreateProcessor;
use App\Api\Processor\TriggerAssetWorkflowProcessor;
use App\Api\Provider\AssetCollectionProvider;
use App\Api\Provider\SearchSuggestionCollectionProvider;
use App\Controller\Core\DeleteAssetByIdsAction;
use App\Controller\Core\DeleteAssetByKeysAction;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Transformer\HighlightableModelInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'asset',
    operations: [
        new GetCollection(
            uriTemplate: '/assets/suggest',
            name: 'suggestions',
            provider: SearchSuggestionCollectionProvider::class,
        ),
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new Put(
            uriTemplate: '/assets/{id}/trigger-workflow',
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            processor: TriggerAssetWorkflowProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/{id}/attributes',
            input: AssetAttributeBatchUpdateInput::class,
            processor: AssetAttributeBatchUpdateProcessor::class,
        ),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
        new Post(
            uriTemplate: '/assets/multiple',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            input: MultipleAssetInput::class,
            output: MultipleAssetOutput::class,
            validate: false,
            name: 'post_multiple',
            processor: MultipleAssetCreateProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/move',
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            input: MoveAssetInput::class,
            name: 'post_move',
            processor: MoveAssetProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/copy',
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            input: CopyAssetInput::class,
            name: 'post_copy',
            processor: CopyAssetProcessor::class,
        ),
        new Delete(
            uriTemplate: '/assets-by-keys',
            controller: DeleteAssetByKeysAction::class,
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            name: 'delete_by_key',
        ),
        new Delete(
            uriTemplate: '/assets',
            controller: DeleteAssetByIdsAction::class,
            name: 'delete_by_ids',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    input: AssetInput::class,
    output: AssetOutput::class,
    provider: AssetCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset extends AbstractUuidEntity implements HighlightableModelInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, WorkspaceItemPrivacyInterface, ESIndexableInterface, ESIndexableDependencyInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use OwnerIdTrait;
    use WorkspacePrivacyTrait;
    final public const GROUP_READ = 'asset:read';
    final public const GROUP_LIST = 'asset:index';
    final public const GROUP_WRITE = 'asset:w';

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $microseconds = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $sequence = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $key = null;

    /**
     * Token sent to Uploader.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $pendingUploadToken = null;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: CollectionAsset::class, cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineCollection $collections = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private ?DoctrineCollection $tags = null;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineCollection $storyCollection = null;

    /**
     * Asset will inherit permissions from this collection.
     */
    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'referenceAssets')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Collection $referenceCollection = null;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: Attribute::class, cascade: ['persist', 'remove'])]
    private ?DoctrineCollection $attributes = null;

    #[ORM\ManyToOne(targetEntity: File::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $source = null;

    private bool $noFileVersion = false;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetRendition::class, cascade: ['remove'])]
    private ?DoctrineCollection $renditions = null;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetFileVersion::class, cascade: ['remove'])]
    private ?DoctrineCollection $fileVersions = null;

    private ?array $highlights = null;

    /**
     * Last update time of attribute.
     */
    #[Groups(['dates'])]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $attributesEditedAt = null;

    /**
     * Last update time of tags.
     */
    #[Groups(['dates'])]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $tagsEditedAt = null;

    /**
     * @param float $now got from microtime(true)
     */
    public function __construct(?float $now = null, ?int $sequence = null)
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->renditions = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->fileVersions = new ArrayCollection();

        /* @var $now float */
        $now ??= microtime(true);
        $createdAt = new \DateTimeImmutable();
        $this->createdAt = $createdAt->setTimestamp((int) floor($now));
        $this->updatedAt = $this->createdAt;
        $this->microseconds = ($now * 1_000_000) % 1_000_000;

        $this->attributesEditedAt = $createdAt;
        $this->tagsEditedAt = $createdAt;

        if (null !== $sequence) {
            $this->sequence = $sequence;
        }
    }

    public function getSource(): ?File
    {
        return $this->source;
    }

    public function setSource(?File $source): void
    {
        $this->source = $source;
    }

    public function getStoryCollection(): ?DoctrineCollection
    {
        return $this->storyCollection;
    }

    public function setStoryCollection(?DoctrineCollection $storyCollection): void
    {
        $this->storyCollection = $storyCollection;
    }

    public function hasChildren(): bool
    {
        return null !== $this->storyCollection;
    }

    /**
     * @return CollectionAsset[]
     */
    public function getCollections(): DoctrineCollection
    {
        return $this->collections;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function addToCollection(Collection $collection, bool $checkUnique = false): CollectionAsset
    {
        if ($collection->getWorkspace() !== $this->getWorkspace()) {
            throw new \InvalidArgumentException('Cannot add to a collection from a different workspace');
        }

        if (null === $this->referenceCollection) {
            $this->setReferenceCollection($collection);
        }

        if ($checkUnique) {
            $duplicates = $this->collections->filter(fn (CollectionAsset $ca): bool => $ca->getCollection() === $collection);

            if (!$duplicates->isEmpty()) {
                return $duplicates->first();
            }
        }

        $assetCollection = new CollectionAsset();
        $assetCollection->setAsset($this);
        $assetCollection->setCollection($collection);

        $this->collections->add($assetCollection);
        $collection->getAssets()->add($assetCollection);

        return $assetCollection;
    }

    /**
     * @internal For admin only
     */
    public function setStartingCollections(DoctrineCollection $collections): void
    {
        foreach ($collections as $collection) {
            $this->addToCollection($collection);
        }
    }

    public function getStartingCollections(): DoctrineCollection
    {
        return $this->collections->map(fn (CollectionAsset $collectionAsset): Collection => $collectionAsset->getCollection());
    }

    /**
     * @return Tag[]
     */
    public function getTags(): DoctrineCollection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): void
    {
        if ($tag->getWorkspace() !== $this->workspace) {
            throw new \LogicException('Cannot add a tag that comes from a different workspace');
        }

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function getTagIds(): array
    {
        return $this->tags->map(fn (Tag $tag): string => $tag->getId())->getValues();
    }

    public function getReferenceCollectionId(): ?string
    {
        return $this->referenceCollection?->getId();

    }

    public function getReferenceCollection(): ?Collection
    {
        return $this->referenceCollection;
    }

    public function setReferenceCollection(?Collection $referenceCollection): void
    {
        $this->referenceCollection = $referenceCollection;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? $this->getId();
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function addAttribute(Attribute $attribute): void
    {
        $attribute->setAsset($this);
        $this->attributes->add($attribute);
    }

    /**
     * @return AssetRendition[]
     */
    public function getRenditions(): DoctrineCollection
    {
        return $this->renditions;
    }

    public function setElasticHighlights(array $highlights)
    {
        $this->highlights = $highlights;

        return $this;
    }

    public function getElasticHighlights()
    {
        return $this->highlights;
    }

    public function isObjectIndexable(): bool
    {
        return null === $this->workspace->getDeletedAt();
    }

    public function isNoFileVersion(): bool
    {
        return $this->noFileVersion;
    }

    public function setNoFileVersion(bool $noFileVersion): void
    {
        $this->noFileVersion = $noFileVersion;
    }

    /**
     * Last update time of any element of the asset.
     */
    #[Groups(['dates'])]
    public function getEditedAt(): ?\DateTimeImmutable
    {
        $date = max(
            $this->attributesEditedAt,
            $this->tagsEditedAt,
            $this->updatedAt,
        );

        if (!$date instanceof \DateTimeImmutable) {
            return \DateTimeImmutable::createFromMutable($date);
        }

        return $date;
    }

    public function getAttributesEditedAt(): ?\DateTimeImmutable
    {
        return $this->attributesEditedAt;
    }

    public function setAttributesEditedAt(?\DateTimeImmutable $attributesEditedAt): void
    {
        $this->attributesEditedAt = $attributesEditedAt;
    }

    public function getPendingUploadToken(): ?string
    {
        return $this->pendingUploadToken;
    }

    public function setPendingUploadToken(?string $pendingUploadToken): void
    {
        $this->pendingUploadToken = $pendingUploadToken;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getMicroseconds(): int
    {
        return $this->microseconds;
    }
}
