<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
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
     * @ORM\Column(type="string", length=255)
     * @Groups({"record_read"})
     */
    private ?string $ownerId = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Workspace", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Workspace $workspace = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CollectionAsset", mappedBy="asset", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $collections = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Collection")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $storyCollection = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $file = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $preview = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $thumb = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
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

    public function getCollections()
    {
        return $this->collections;
    }
}
