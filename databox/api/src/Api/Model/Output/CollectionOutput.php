<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

class CollectionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    /**
     * @Groups({"collection:index", "collection:read", "workspace:index", "workspace:read"})
     */
    protected array $capabilities = [];

    /**
     * @Groups({"collection:index", "collection:read", "asset:index", "asset:read", "workspace:index", "workspace:read"})
     */
    private ?string $title = null;

    /**
     * @Groups({"collection:index", "collection:read", "workspace:index", "workspace:read"})
     */
    private ?string $ownerId = null;

    /**
     * @Groups({"collection:index", "collection:read", "workspace:index", "workspace:read"})
     */
    private int $privacy;

    /**
     * @Groups({"collection:parent"})
     */
    private ?self $parent = null;

    /**
     * @MaxDepth(2)
     * @Groups({"collection:index", "collection:children", "workspace:index"})
     */
    private $children;

    /**
     * @MaxDepth(1)
     * @Groups({"collection:index", "collection:read", "workspace:index", "workspace:read"})
     */
    private $workspace;

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

    public function setPrivacy(int $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function getPrivacy(): int
    {
        return $this->privacy;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function setChildren($children): void
    {
        $this->children = $children;
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
