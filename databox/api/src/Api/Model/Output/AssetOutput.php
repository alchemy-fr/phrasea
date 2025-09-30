<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use ApiPlatform\Metadata\ApiProperty;
use App\Api\Filter\Group\GroupValue;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Share;
use App\Entity\Discussion\Thread;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;
    use ExtraMetadataDTOTrait;

    #[Groups(['_', Asset::GROUP_LIST])]
    #[ApiProperty(identifier: true)]
    protected string $id;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'object',
        'properties' => [
            'canEdit' => 'boolean',
            'canDelete' => 'boolean',
            'canEditPermissions' => 'boolean',
        ],
    ])]
    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    protected array $capabilities = [];

    /**
     * @var AttributeOutput[]
     */
    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    protected ?array $attributes = null;

    #[Groups([Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Asset::GROUP_STORY,
        WebhookSerializationInterface::DEFAULT_GROUP,
        Share::GROUP_PUBLIC_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $title = null;

    #[Groups([Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Asset::GROUP_STORY,
        WebhookSerializationInterface::DEFAULT_GROUP,
        Share::GROUP_READ,
        Share::GROUP_PUBLIC_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $resolvedTitle = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    private ?string $titleHighlight = null;

    #[Groups([Asset::GROUP_READ])]
    public ?Thread $thread = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    public ?string $threadKey = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    private int $privacy;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    private ?bool $pendingSourceFile = null;

    #[Groups([Asset::GROUP_READ, Asset::GROUP_LIST])]
    private ?string $pendingUploadToken = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    private $workspace;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    public ?Collection $storyCollection = null;

    /**
     * Appears in these stories.
     */
    #[Groups([Asset::GROUP_READ])]
    public ?Collection $stories = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    private array $tags;

    #[Groups([Asset::GROUP_READ, Asset::GROUP_LIST])]
    public ?UserOutput $owner = null;

    #[Groups([Asset::GROUP_READ])]
    public $referenceCollection;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ])]
    private array $collections;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    private ?File $source = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $original = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $preview = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $thumbnail = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $thumbnailActive = null;

    #[Groups(['dates'])]
    private \DateTimeImmutable $editedAt;

    #[Groups(['dates'])]
    private \DateTimeImmutable $attributesEditedAt;

    #[Groups([Asset::GROUP_READ])]
    public ?array $topicSubscriptions = null;

    /**
     * Used for result grouping.
     */
    #[Groups(['_'])]
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

    public function getEditedAt(): \DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function setEditedAt(\DateTimeImmutable $editedAt): void
    {
        $this->editedAt = $editedAt;
    }

    public function getAttributesEditedAt(): \DateTimeImmutable
    {
        return $this->attributesEditedAt;
    }

    public function setAttributesEditedAt(\DateTimeImmutable $attributesEditedAt): void
    {
        $this->attributesEditedAt = $attributesEditedAt;
    }

    public function isPendingSourceFile(): bool
    {
        return $this->pendingSourceFile;
    }

    public function setPendingSourceFile(bool $pendingSourceFile): void
    {
        $this->pendingSourceFile = $pendingSourceFile;
    }

    public function getPendingUploadToken(): ?string
    {
        return $this->pendingUploadToken;
    }

    public function setPendingUploadToken(?string $pendingUploadToken): void
    {
        $this->pendingUploadToken = $pendingUploadToken;
    }
}
