<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\SearchableEntityInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\TranslatableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Input\AssetInput;
use LogicException;

/**
 * @ApiResource(
 *  shortName="asset",
 *  normalizationContext={"groups"={"_", "asset:index"}},
 *  output=AssetOutput::class,
 *  input=AssetInput::class,
 * )
 * @ORM\Entity(repositoryClass="App\Repository\AssetRepository")
 */
class Asset extends AbstractUuidEntity implements AclObjectInterface, TranslatableInterface, SearchableEntityInterface, WorkspaceItemPrivacyInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use TranslatableTrait;
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
     * Asset will inherits permissions from this collection.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Collection $referenceCollection = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $file = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $preview = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $thumb = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): void
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

    public function getPreview(): ?File
    {
        return $this->preview;
    }

    public function setPreview(?File $preview): void
    {
        $this->preview = $preview;
    }

    public function getThumb(): ?File
    {
        return $this->thumb;
    }

    public function setThumb(?File $thumb): void
    {
        $this->thumb = $thumb;
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

    public function addToCollection(Collection $collection): CollectionAsset
    {
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
            throw new LogicException('Cannot add a tag that comes from a different workspace');
        }

        $this->tags->add($tag);
    }

    public function getTagIds(): array
    {
        return $this->tags->map(function (Tag $tag): string {
           return $tag->getId();
        })->getValues();
    }

    public function getReferenceCollection(): ?Collection
    {
        return $this->referenceCollection;
    }

    public function setReferenceCollection(?Collection $referenceCollection): void
    {
        $this->referenceCollection = $referenceCollection;
    }
}
