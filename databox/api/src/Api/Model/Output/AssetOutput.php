<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Api\Filter\Group\GroupValue;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use DateTimeImmutable;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    /**
     * @ApiProperty(attributes={
     *  "openapi_context"={
     *     "type"="object",
     *     "properties"={
     *       "canEdit"={
     *         "type"="boolean"
     *       },
     *       "canDelete"={
     *         "type"="boolean"
     *       },
     *       "canEditPermissions"={
     *         "type"="boolean"
     *       },
     *     }
     *  },
     *  "json_schema_context"={
     *     "type"="object",
     *     "properties"={
     *       "canEdit"="boolean",
     *       "canDelete"="boolean",
     *       "canEditPermissions"="boolean",
     *     }
     * }
     * })
     * @Groups({"asset:index", "asset:read"})
     */
    protected array $capabilities = [];

    /**
     * @var AttributeOutput[]
     * @Groups({"asset:index", "asset:read"})
     */
    protected ?array $attributes;

    /**
     * @Groups({"asset:index", "asset:read", "Webhook"})
     */
    private ?string $title = null;

    /**
     * @Groups({"asset:index", "asset:read", "Webhook"})
     */
    private ?string $resolvedTitle = null;

    /**
     * @Groups({"asset:index", "asset:read"})
     */
    private ?string $titleHighlight = null;

    /**
     * @Groups({"asset:index", "asset:read", "Webhook"})
     */
    private int $privacy;

    /**
     * @Groups({"asset:index", "asset:read", "Webhook"})
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
    private $source = null;

    /**
     * @var AssetRendition
     * @Groups({"asset:index", "asset:read"})
     */
    private $original = null;

    /**
     * @var AssetRendition
     * @Groups({"asset:index", "asset:read"})
     */
    private $preview = null;

    /**
     * @var AssetRendition
     * @Groups({"asset:index", "asset:read"})
     */
    private $thumbnail = null;

    /**
     * @var AssetRendition
     * @Groups({"asset:index", "asset:read"})
     */
    private $thumbnailActive = null;

    /**
     * @ApiProperty()
     * @Groups({"dates"})
     */
    private DateTimeImmutable $editedAt;

    /**
     * @ApiProperty()
     * @Groups({"dates"})
     */
    private DateTimeImmutable $attributesEditedAt;

    /**
     * Used for result grouping.
     *
     * @Groups({"_"})
     */
    private ?GroupValue $groupValue = null;

    public function getOriginal(): ?AssetRendition
    {
        return $this->original;
    }

    public function setOriginal(?AssetRendition $original): void
    {
        $this->original = $original;
    }

    public function getPreview(): ?AssetRendition
    {
        return $this->preview;
    }

    public function setPreview(?AssetRendition $preview): void
    {
        $this->preview = $preview;
    }

    public function getThumbnail(): ?AssetRendition
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?AssetRendition $thumbnail): void
    {
        $this->thumbnail = $thumbnail;
    }

    public function getThumbnailActive(): ?AssetRendition
    {
        return $this->thumbnailActive;
    }

    public function setThumbnailActive(?AssetRendition $thumbnailActive): void
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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getTitleHighlight(): ?string
    {
        return $this->titleHighlight;
    }

    public function setTitleHighlight(?string $titleHighlight): void
    {
        $this->titleHighlight = $titleHighlight;
    }

    public function getResolvedTitle(): ?string
    {
        return $this->resolvedTitle;
    }

    public function setResolvedTitle(?string $resolvedTitle): void
    {
        $this->resolvedTitle = $resolvedTitle;
    }

    public function getGroupValue(): ?GroupValue
    {
        return $this->groupValue;
    }

    public function setGroupValue(?GroupValue $groupValue): void
    {
        $this->groupValue = $groupValue;
    }

    public function getSource(): ?File
    {
        return $this->source;
    }

    public function setSource(?File $source): void
    {
        $this->source = $source;
    }

    public function getEditedAt(): DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function setEditedAt(DateTimeImmutable $editedAt): void
    {
        $this->editedAt = $editedAt;
    }

    public function getAttributesEditedAt(): DateTimeImmutable
    {
        return $this->attributesEditedAt;
    }

    public function setAttributesEditedAt(DateTimeImmutable $attributesEditedAt): void
    {
        $this->attributesEditedAt = $attributesEditedAt;
    }
}
