<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\ESBundle\Indexer\ESIndexableDeleteDependencyInterface;
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
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Input\CollectionInput;
use App\Api\Model\Input\FollowInput;
use App\Api\Model\Output\CollectionOutput;
use App\Api\Model\Output\ESDocumentStateOutput;
use App\Api\Processor\FollowProcessor;
use App\Api\Processor\ItemElasticsearchDocumentSyncProcessor;
use App\Api\Processor\MoveCollectionProcessor;
use App\Api\Processor\UnfollowProcessor;
use App\Api\Provider\CollectionProvider;
use App\Api\Provider\ItemElasticsearchDocumentProvider;
use App\Doctrine\Listener\SoftDeleteableInterface;
use App\Entity\FollowableInterface;
use App\Entity\ObjectTitleInterface;
use App\Entity\Traits\DeletedAtTrait;
use App\Entity\Traits\ExtraMetadataTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\NotificationSettingsTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\TranslationsTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\CollectionRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ApiResource(
    shortName: 'collection',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [
                    self::GROUP_READ,
                    self::GROUP_ABSOLUTE_TITLE,
                ],
            ],
            security: 'is_granted("'.AbstractVoter::READ.'", object)'
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("EDIT", object)',
        ),
        new Patch(security: 'is_granted("EDIT", object)'),
        new Put(
            uriTemplate: '/collections/{id}/move/{dest}',
            uriVariables: [
                'dest' => new Link(fromClass: Collection::class, identifiers: ['id'], expandedValue: '{dest}'),
                'id' => new Link(fromClass: Collection::class, identifiers: ['id']),
            ],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'dest',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'The destination collection ID',
                    ],
                ],
            ],
            security: 'is_granted("EDIT", object)',
            deserialize: false,
            name: 'put_move',
            processor: MoveCollectionProcessor::class
        ),
        new GetCollection(
            parameters: [
                'workspaces' => new QueryParameter(
                    schema: ['type' => 'array<string>'],
                    description: 'Workspaces ID',
                ),
                'parents' => new QueryParameter(
                    schema: ['type' => 'array<string>'],
                    description: 'Parent collections',
                ),
                'parent' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Parent collection',
                ),
            ]
        ),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
        new Get(
            uriTemplate: '/collections/{id}/es-document',
            output: ESDocumentStateOutput::class,
            name: 'collection_es_document',
            provider: ItemElasticsearchDocumentProvider::class,
        ),
        new Post(
            uriTemplate: '/collections/{id}/es-document-sync',
            name: 'collection_sync_es_document',
            processor: ItemElasticsearchDocumentSyncProcessor::class,
        ),
        new Post(
            uriTemplate: '/collections/{id}/follow',
            input: FollowInput::class,
            processor: FollowProcessor::class,
        ),
        new Post(
            uriTemplate: '/collections/{id}/unfollow',
            input: FollowInput::class,
            processor: UnfollowProcessor::class,
        ),
    ],
    normalizationContext: [
        'enable_max_depth' => true,
        'groups' => [
            self::GROUP_LIST,
            self::GROUP_CHILDREN,
        ],
    ],
    input: CollectionInput::class,
    output: CollectionOutput::class,
    provider: CollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_coll_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\Entity(repositoryClass: CollectionRepository::class)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', hardDelete: false)]
class Collection extends AbstractUuidEntity implements FollowableInterface, SoftDeleteableInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, ESIndexableDependencyInterface, ESIndexableDeleteDependencyInterface, ESIndexableInterface, ObjectTitleInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;
    use WorkspaceTrait;
    use OwnerIdTrait;
    use LocaleTrait;
    use WorkspacePrivacyTrait;
    use NotificationSettingsTrait;
    use ExtraMetadataTrait;
    use TranslationsTrait;

    final public const string GROUP_READ = 'coll:read';
    final public const string GROUP_LIST = 'coll:index';
    final public const string GROUP_CHILDREN = 'coll:ic';
    final public const string GROUP_ABSOLUTE_TITLE = 'coll:absTitle';

    final public const string EVENT_ASSET_ADD = 'asset_add';
    final public const string EVENT_ASSET_UPDATE = 'asset_update';
    final public const string EVENT_ASSET_NEW_COMMENT = 'asset_new_comment';
    final public const string EVENT_ASSET_REMOVE = 'asset_remove';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    #[MaxDepth(1)]
    private ?self $parent = null;

    /**
     * @var self[]|DoctrineCollection
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[MaxDepth(1)]
    private ?DoctrineCollection $children = null;

    /**
     * Virtual.
     */
    private ?bool $hasChildren = null;

    #[ORM\OneToMany(mappedBy: 'collection', targetEntity: CollectionAsset::class, cascade: ['persist'])]
    private ?DoctrineCollection $assets = null;

    #[ORM\OneToMany(mappedBy: 'referenceCollection', targetEntity: Asset::class)]
    private ?DoctrineCollection $referenceAssets = null;

    #[ORM\OneToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Asset $storyAsset = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'collections')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Workspace $workspace = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: Types::STRING, length: 4096, nullable: true)]
    private ?string $key = null;

    #[Groups(['_'])]
    private ?array $relationExtraMetadata = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->referenceAssets = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        if (null !== $this->deletedAt) {
            return sprintf('(being deleted...) %s', $this->title ?: $this->getId());
        }

        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if (null !== $parent && $parent->getWorkspace() !== $this->getWorkspace()) {
            throw new BadRequestHttpException('Cannot add a sub-collection in a different workspace');
        }

        $this->parent = $parent;
    }

    public function getSortName(): string
    {
        return strtolower($this->title ?? '');
    }

    /**
     * @return Collection[]
     */
    public function getChildren(): DoctrineCollection
    {
        return $this->children;
    }

    /**
     * @return CollectionAsset[]
     */
    public function getAssets(): DoctrineCollection
    {
        return $this->assets;
    }

    public function getAbsolutePath(): string
    {
        $path = '/'.$this->getId();
        if (null !== $this->parent) {
            return $this->parent->getAbsolutePath().$path;
        }

        return $path;
    }

    public function getPathDepth(): int
    {
        $depth = 0;
        $ptr = $this;
        while (null !== $ptr = $ptr->parent) {
            ++$depth;
        }

        return $depth;
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Used by ES.
     */
    public function getPrivacyRoots(): array
    {
        return array_keys(array_filter($this->computePrivacyRoots(), fn (bool $r): bool => $r));
    }

    private function computePrivacyRoots(): array
    {
        $roots = [];
        for ($i = WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE; $i <= WorkspaceItemPrivacyInterface::PUBLIC; ++$i) {
            $roots[$i] = $this->privacy >= $i;
        }

        if (null !== $this->parent) {
            $parentRoots = $this->parent->computePrivacyRoots();
            foreach ($parentRoots as $i => $root) {
                if ($root) {
                    $roots[$i] = false;
                }
            }
        }

        return $roots;
    }

    public function getInheritedPrivacy(): ?int
    {
        return $this->parent?->getBestPrivacyInParentHierarchy();
    }

    public function getBestPrivacyInParentHierarchy(): int
    {
        $bestPrivacy = $this->privacy;

        // Early return if best
        if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
            return $this->privacy;
        }

        if (
            null !== $this->parent
            && ($better = $this->parent->getBestPrivacyInParentHierarchy()) > $bestPrivacy
        ) {
            return $better;
        }

        return $bestPrivacy;
    }

    public function isVisible(): bool
    {
        return $this->privacy >= WorkspaceItemPrivacyInterface::PRIVATE;
    }

    public function getAbsoluteTitle(): ?string
    {
        $path = $this->getTitle();
        if (null !== $this->parent) {
            return $this->parent->getAbsoluteTitle().' / '.$path;
        }

        return $path;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function __toString(): string
    {
        return $this->getAbsoluteTitle() ?? $this->getId();
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getHasChildren(): ?bool
    {
        return $this->hasChildren;
    }

    /**
     * @internal
     */
    public function setHasChildren(?bool $hasChildren): void
    {
        $this->hasChildren = $hasChildren;
    }

    public function getIndexableDeleteDependencies(): array
    {
        if ($this->parent) {
            return [$this->parent];
        }

        return [];
    }

    public function isObjectIndexable(): bool
    {
        return null === $this->workspace->getDeletedAt() && !$this->isStory();
    }

    public function getTopicKeys(): array
    {
        $id = $this->getId();

        return [
            self::getTopicKey(self::EVENT_ASSET_ADD, $id),
            self::getTopicKey(self::EVENT_ASSET_REMOVE, $id),
            self::getTopicKey(self::EVENT_ASSET_NEW_COMMENT, $id),
            self::getTopicKey(self::EVENT_ASSET_UPDATE, $id),
        ];
    }

    public static function getTopicKey(string $event, string $id): string
    {
        return 'collection:'.$id.':'.$event;
    }

    public function getObjectTitle(): string
    {
        return sprintf('Collection %s', $this->getTitle() ?: $this->getId());
    }

    public function getRelationExtraMetadata(): array
    {
        return $this->relationExtraMetadata ?? [];
    }

    public function setRelationExtraMetadata(?array $relationExtraMetadata): void
    {
        $this->relationExtraMetadata = $relationExtraMetadata;
    }

    public function isStory(): bool
    {
        return null !== $this->storyAsset;
    }

    public function getStoryAsset(): ?Asset
    {
        return $this->storyAsset;
    }

    /*
     * @internal Call setStoryCollection only
     */
    public function setStoryAsset(?Asset $storyAsset): void
    {
        if ($storyAsset && null !== $this->getTitle()) {
            throw new \LogicException('Story collection should not have a title');
        }
        $this->storyAsset = $storyAsset;
    }
}
