<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetOutput extends AbstractUuidDTO
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

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
    private bool $public = false;

    /**
     * @Groups({"asset:read"})
     */
    private array $tags;

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

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * @return TagDTO[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
