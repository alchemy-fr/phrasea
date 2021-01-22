<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Symfony\Component\Serializer\Annotation\Groups;

class CollectionOutput extends AbstractUuidDTO
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     * @Groups({"collection:index", "collection:read"})
     */
    private ?string $title = null;

    /**
     * @Groups({"collection:index", "collection:read"})
     */
    private ?string $ownerId = null;

    /**
     * @Groups({"collection:index", "collection:read"})
     */
    private bool $public = false;

    /**
     * @Groups({"collection:parent"})
     */
    private ?self $parent = null;

    /**
     * @Groups({"collection:index", "collection:children"})
     */
    private array $children;

    /**
     * @Groups({"collection:assets"})
     */
    private array $assets;

    public function getTitle(): ?string
    {
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
        $this->parent = $parent;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getAssets(): array
    {
        return $this->assets;
    }

    public function setAssets(array $assets): void
    {
        $this->assets = $assets;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }
}
