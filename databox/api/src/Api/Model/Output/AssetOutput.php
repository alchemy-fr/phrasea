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
use App\Entity\Core\AssetAttachment;
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
    #[Groups([Asset::GROUP_LIST])]
    protected array $capabilities = [];

    /**
     * @var AttributeOutput[]
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    protected ?array $attributes = null;

    /**
     * @var AssetAttachment[]
     */
    #[Groups([Asset::GROUP_READ])]
    public $attachments;

    #[Groups([
        Asset::GROUP_LIST,
        Asset::GROUP_STORY,
        WebhookSerializationInterface::DEFAULT_GROUP,
        Share::GROUP_PUBLIC_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $title = null;

    #[Groups([
        Asset::GROUP_LIST,
        Asset::GROUP_STORY,
        WebhookSerializationInterface::DEFAULT_GROUP,
        Share::GROUP_READ,
        Share::GROUP_PUBLIC_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $resolvedTitle = null;

    #[Groups([Asset::GROUP_LIST])]
    private ?string $titleHighlight = null;

    #[Groups([Asset::GROUP_READ])]
    public ?Thread $thread = null;

    #[Groups([Asset::GROUP_LIST])]
    public ?string $threadKey = null;

    #[Groups([Asset::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    private int $privacy;

    #[Groups([Asset::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    public bool $deleted;

    #[Groups([Asset::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    private $workspace;

    #[Groups([Asset::GROUP_LIST])]
    public ?Collection $storyCollection = null;

    #[Groups([Asset::GROUP_LIST])]
    private array $tags;

    #[Groups([Asset::GROUP_LIST])]
    public ?UserOutput $owner = null;

    #[Groups([Asset::GROUP_LIST])]
    public $referenceCollection;

    #[Groups([Asset::GROUP_LIST])]
    private array $collections;

    /**
     * @var FileOutput|null
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    private ?File $source = null;

    /**
     * @var AssetRenditionOutput|null
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $main = null;

    /**
     * @var AssetRenditionOutput|null
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $preview = null;

    /**
     * @var AssetRenditionOutput|null
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $thumbnail = null;

    /**
     * @var AssetRenditionOutput|null
     */
    #[Groups([Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    private ?AssetRendition $animatedThumbnail = null;

    #[Groups(['dates'])]
    private \DateTimeImmutable $editedAt;

    #[Groups(['dates'])]
    private \DateTimeImmutable $attributesEditedAt;

    #[Groups([Asset::GROUP_READ])]
    public ?array $topicSubscriptions = null;

    #[Groups([Asset::GROUP_READ])]
    public ?string $trackingId = null;

    /**
     * Used for result grouping.
     */
    #[Groups(['_'])]
    private ?GroupValue $groupValue = null;

    public function getMain(): ?AssetRendition
    {
        return $this->main;
    }

    public function setMain(?AssetRendition $main): void
    {
        $this->main = $main;
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

    public function getAnimatedThumbnail(): ?AssetRendition
    {
        return $this->animatedThumbnail;
    }

    public function setAnimatedThumbnail(?AssetRendition $animatedThumbnail): void
    {
        $this->animatedThumbnail = $animatedThumbnail;
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
}
