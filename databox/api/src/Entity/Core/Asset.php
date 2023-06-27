<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\CopyAssetInput;
use App\Api\Model\Input\MoveAssetInput;
use App\Entity\AbstractUuidEntity;
use App\Entity\ESIndexableInterface;
use App\Entity\SearchableEntityInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Transformer\HighlightableModelInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AssetRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_ws_key",columns={"workspace_id", "key"})})
 */
class Asset extends AbstractUuidEntity implements HighlightableModelInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, SearchableEntityInterface, WorkspaceItemPrivacyInterface, ESIndexableInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use WorkspacePrivacyTrait;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $microseconds = 0;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $sequence = 0;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string", length=36)
     */
    private ?string $ownerId = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $key = null;

    /**
     * Token sent to Uploader.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $pendingUploadToken = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\CollectionAsset", mappedBy="asset", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $collections = null;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Tag")
     */
    private ?DoctrineCollection $tags = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $storyCollection = null;

    /**
     * Asset will inherit permissions from this collection.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection", inversedBy="referenceAssets")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Collection $referenceCollection = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Attribute", mappedBy="asset", cascade={"persist", "remove"})
     */
    private ?DoctrineCollection $attributes = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File", cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $source = null;

    private bool $noFileVersion = false;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\AssetRendition", mappedBy="asset", cascade={"remove"})
     */
    private ?DoctrineCollection $renditions = null;

    private ?array $highlights = null;

    public ?AssetAttributeBatchUpdateInput $attributeActions = null;

    public ?CopyAssetInput $copyAction = null;
    public ?MoveAssetInput $moveAction = null;

    /**
     * Last update time of attribute.
     *
     * @ORM\Column(type="datetime_immutable")
     */
    #[Groups(['dates'])]
    protected ?\DateTimeImmutable $attributesEditedAt = null;

    /**
     * Last update time of tags.
     *
     * @ORM\Column(type="datetime_immutable")
     */
    #[Groups(['dates'])]
    protected ?\DateTimeImmutable $tagsEditedAt = null;

    /**
     * @param float $now got from microtime(true)
     */
    public function __construct(float $now = null, int $sequence = null)
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->renditions = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->attributes = new ArrayCollection();

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

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
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
        if (!$this->referenceCollection) {
            return null;
        }

        return $this->referenceCollection->getId();
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
