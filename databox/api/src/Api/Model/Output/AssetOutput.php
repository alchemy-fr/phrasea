<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\File;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    protected array $capabilities = [];

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    protected DateTime $createdAt;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    protected DateTime $updatedAt;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private ?string $title = null;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private int $privacy;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private $workspace;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private array $tags;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private array $collections;

    /**
     * @var File
     * @Groups({"asset:index", "asset:read"})
     */
    private $original = null;

    /**
     * @var File
     * @Groups({"asset:index", "asset:read"})
     */
    private $preview = null;

    /**
     * @var File
     * @Groups({"asset:index", "asset:read"})
     */
    private $thumbnail = null;

    /**
     * @var File
     * @Groups({"asset:index", "asset:read"})
     */
    private $thumbnailActive = null;

    public function getOriginal()
    {
        return $this->original;
    }

    public function setOriginal($original): void
    {
        $this->original = $original;
    }

    public function getPreview(): ?File
    {
        return $this->preview;
    }

    public function setPreview(?File $preview): void
    {
        $this->preview = $preview;
    }

    public function getThumbnail(): ?File
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?File $thumbnail): void
    {
        $this->thumbnail = $thumbnail;
    }

    public function getThumbnailActive(): ?File
    {
        return $this->thumbnailActive;
    }

    public function setThumbnailActive(?File $thumbnailActive): void
    {
        $this->thumbnailActive = $thumbnailActive;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getPrivacy(): int
    {
        return $this->privacy;
    }

    public function setPrivacy(int $privacy): void
    {
        $this->privacy = $privacy;
    }

    /**
     * @return TagOutput[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getCollections(): array
    {
        return $this->collections;
    }

    public function setCollections(array $collections): void
    {
        $this->collections = $collections;
    }

    public function getWorkspace()
    {
        return $this->workspace;
    }

    public function setWorkspace($workspace): void
    {
        $this->workspace = $workspace;
    }
}
