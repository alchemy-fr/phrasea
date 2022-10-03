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
use InvalidArgumentException;
use LogicException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AssetRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_ws_key",columns={"workspace_id", "key"})})
 */
class Asset extends AbstractUuidEntity implements HighlightableModelInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, SearchableEntityInterface, WorkspaceItemPrivacyInterface, ESIndexableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use WorkspacePrivacyTrait;

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
    private ?File $file = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\AssetRendition", mappedBy="asset", cascade={"remove"})
     */
    private ?DoctrineCollection $renditions = null;

    private ?array $highlights = null;

    public ?AssetAttributeBatchUpdateInput $attributeActions = null;

    public ?CopyAssetInput $copyAction = null;
    public ?MoveAssetInput $moveAction = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->renditions = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->attributes = new ArrayCollection();
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
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
            throw new InvalidArgumentException('Cannot add to a collection from a different workspace');
        }

        if (null === $this->referenceCollection) {
            $this->setReferenceCollection($collection);
        }

        if ($checkUnique) {
            $duplicates = $this->collections->filter(function (CollectionAsset $ca) use ($collection): bool {
                return $ca->getCollection() === $collection;
            });

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
        return $this->collections->map(function (CollectionAsset $collectionAsset): Collection {
            return $collectionAsset->getCollection();
        });
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
            throw new LogicException('Cannot add a tag that comes from a different workspace');
        }

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function getTagIds(): array
    {
        return $this->tags->map(function (Tag $tag): string {
            return $tag->getId();
        })->getValues();
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

    public function __toString()
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
}
