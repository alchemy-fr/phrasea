<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
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
    private array $tags;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private array $collections;

    /**
     * @Groups({"asset:read"})
     */
    private ?FileOutput $file = null;

    /**
     * @Groups({"asset:read"})
     */
    private ?FileOutput $preview = null;

    /**
     * @Groups({"asset:read"})
     */
    private ?FileOutput $thumb = null;

    public function getFile(): ?FileOutput
    {
        return $this->file;
    }

    public function setFile(?FileOutput $file): void
    {
        $this->file = $file;
    }

    public function getPreview(): ?FileOutput
    {
        return $this->preview;
    }

    public function setPreview(?FileOutput $preview): void
    {
        $this->preview = $preview;
    }

    public function getThumb(): ?FileOutput
    {
        return $this->thumb;
    }

    public function setThumb(?FileOutput $thumb): void
    {
        $this->thumb = $thumb;
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
}
