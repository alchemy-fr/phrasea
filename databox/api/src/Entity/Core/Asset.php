<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Asset extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $public = false;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"asset_read"})
     */
    private ?string $ownerId = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Workspace $workspace = null;

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

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
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

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getWorkspaceId(): string
    {
        return $this->workspace->getId();
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
        $this->tags->add($tag);
    }

    public function getTagIds(): array
    {
        return $this->tags->map(function (Tag $tag): string {
           return $tag->getESId();
        })->getValues();
    }
}
