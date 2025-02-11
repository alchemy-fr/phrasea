<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\CopyAssetInput;
use App\Api\Model\Input\FollowInput;
use App\Api\Model\Input\MoveAssetInput;
use App\Api\Model\Input\MultipleAssetInput;
use App\Api\Model\Input\PrepareDeleteAssetsInput;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\ESDocumentStateOutput;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Api\Model\Output\PrepareDeleteAssetsOutput;
use App\Api\Processor\AssetAttributeBatchUpdateProcessor;
use App\Api\Processor\CopyAssetProcessor;
use App\Api\Processor\FollowProcessor;
use App\Api\Processor\ItemElasticsearchDocumentSyncProcessor;
use App\Api\Processor\MoveAssetProcessor;
use App\Api\Processor\MultipleAssetCreateProcessor;
use App\Api\Processor\PrepareDeleteAssetProcessor;
use App\Api\Processor\PrepareSubstitutionProcessor;
use App\Api\Processor\RemoveAssetFromCollectionProcessor;
use App\Api\Processor\TriggerAssetWorkflowProcessor;
use App\Api\Processor\UnfollowProcessor;
use App\Api\Provider\AssetCollectionProvider;
use App\Api\Provider\ItemElasticsearchDocumentProvider;
use App\Api\Provider\SearchSuggestionCollectionProvider;
use App\Controller\Core\DeleteAssetByIdsAction;
use App\Controller\Core\DeleteAssetByKeysAction;
use App\Entity\FollowableInterface;
use App\Entity\ObjectTitleInterface;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\NotificationSettingsTrait;
use App\Entity\Traits\OwnerIdTrait;
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
                'groups' => [self::GROUP_READ, Collection::GROUP_ABSOLUTE_TITLE],
            ],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Delete(
            uriTemplate: '/assets/{id}/collections/{collectionId}',
            uriVariables: [
                'collectionId' => new Link(fromClass: Collection::class, identifiers: ['id'], expandedValue: '{collectionId}'),
                'id' => new Link(fromClass: Asset::class, identifiers: ['id']),
            ],
            name: 'remove_from_collection',
            provider: AssetCollectionProvider::class,
            processor: RemoveAssetFromCollectionProcessor::class,
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
        new Put(
            uriTemplate: '/assets/{id}/prepare-substitution',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            output: AssetOutput::class,
            provider: AssetCollectionProvider::class,
            processor: PrepareSubstitutionProcessor::class,
        ),
        new GetCollection(),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ, Collection::GROUP_ABSOLUTE_TITLE],
            ],
            securityPostDenormalize: 'is_granted("CREATE", object)',
            validate: true,
        ),
        new Post(
            uriTemplate: '/assets/multiple',
            normalizationContext: [
                'groups' => [self::GROUP_READ, Collection::GROUP_ABSOLUTE_TITLE],
            ],
            input: MultipleAssetInput::class,
            output: MultipleAssetOutput::class,
            validate: false,
            name: 'post_multiple',
            processor: MultipleAssetCreateProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/prepare-delete',
            normalizationContext: [
                'groups' => [
                    self::GROUP_LIST,
                    Collection::GROUP_ABSOLUTE_TITLE,
                ],
            ],
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            input: PrepareDeleteAssetsInput::class,
            output: PrepareDeleteAssetsOutput::class,
            name: 'prepare_delete',
            processor: PrepareDeleteAssetProcessor::class,
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
        new Get(
            uriTemplate: '/assets/{id}/es-document',
            output: ESDocumentStateOutput::class,
            name: 'asset_es_document',
            provider: ItemElasticsearchDocumentProvider::class,
        ),
        new Post(
            uriTemplate: '/assets/{id}/es-document-sync',
            name: 'asset_sync_es_document',
            processor: ItemElasticsearchDocumentSyncProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/{id}/follow',
            input: FollowInput::class,
            processor: FollowProcessor::class,
        ),
        new Post(
            uriTemplate: '/assets/{id}/unfollow',
            input: FollowInput::class,
            processor: UnfollowProcessor::class,
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
class Asset extends AbstractUuidEntity implements FollowableInterface, HighlightableModelInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, WorkspaceItemPrivacyInterface, ESIndexableInterface, ESIndexableDependencyInterface, ObjectTitleInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use OwnerIdTrait;
    use WorkspacePrivacyTrait;
    use NotificationSettingsTrait;
    final public const string GROUP_READ = 'asset:read';
    final public const string GROUP_LIST = 'asset:index';
    final public const string GROUP_WRITE = 'asset:w';

    final public const string EVENT_UPDATE = 'update';
    final public const string EVENT_DELETE = 'delete';
    final public const string EVENT_NEW_COMMENT = 'new_comment';

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

    public function addToCollection(Collection $collection, bool $checkUnique = false, bool $assignReferenceIfNull = false): CollectionAsset
    {
        if ($collection->getWorkspace() !== $this->getWorkspace()) {
            throw new \InvalidArgumentException('Cannot add to a collection from a different workspace');
        }

        if ($assignReferenceIfNull && null === $this->referenceCollection) {
            $this->referenceCollection = $collection;
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

    public function getTopicKeys(): array
    {
        $id = $this->getId();

        return [
            self::getTopicKey(self::EVENT_UPDATE, $id),
            self::getTopicKey(self::EVENT_DELETE, $id),
            self::getTopicKey(self::EVENT_NEW_COMMENT, $id),
        ];
    }

    public static function getTopicKey(string $event, string $id): string
    {
        return 'asset:'.$id.':'.$event;
    }

    public function getObjectTitle(): string
    {
        return sprintf('Asset %s', $this->getTitle() ?? $this->getId());
    }
}
