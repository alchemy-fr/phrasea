<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Attribute\Context;

class CollectionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;
    use ExtraMetadataDTOTrait;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'object',
        'properties' => [
            'canEdit' => 'boolean',
            'canDelete' => 'boolean',
            'canEditPermissions' => 'boolean',
        ],
    ])]
    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    protected array $capabilities = [];

    #[Groups([
        Collection::GROUP_LIST,
        Collection::GROUP_READ,
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $title = null;

    #[Groups([
        Collection::GROUP_LIST,
        Collection::GROUP_READ,
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    public ?string $titleTranslated = null;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    private ?string $ownerId = null;

    #[Groups([Collection::GROUP_READ])]
    public ?UserOutput $owner = null;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    private int $privacy;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    public ?int $inheritedPrivacy = null;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    public bool $shared;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    public bool $public;

    #[Groups([Collection::GROUP_ABSOLUTE_TITLE])]
    public ?string $absoluteTitle = null;

    #[Groups([Collection::GROUP_ABSOLUTE_TITLE])]
    public ?string $absoluteTitleTranslated = null;

    #[Groups(['collection:parent'])]
    private ?self $parent = null;

    #[MaxDepth(2)]
    #[Groups([Collection::GROUP_LIST, 'collection:children', Workspace::GROUP_LIST])]
    private $children;

    #[MaxDepth(1)]
    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    private $workspace;

    #[Groups([Collection::GROUP_LIST, Collection::GROUP_READ, Workspace::GROUP_LIST, Workspace::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
    #[MaxDepth(1)]
    #[Context(
        normalizationContext: ['groups' => [Collection::GROUP_READ, Asset::GROUP_STORY, '_']],
    )]
    private ?Asset $storyAsset;

    #[Groups(['_'])]
    public ?array $relationExtraMetadata = null;

    #[Groups([Collection::GROUP_READ])]
    public ?array $topicSubscriptions = null;

    #[Groups([Collection::GROUP_READ])]
    public ?array $translations = null;

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

    public function getStoryAsset(): ?Asset
    {
        return $this->storyAsset;
    }

    public function setStoryAsset(?Asset $storyAsset): void
    {
        $this->storyAsset = $storyAsset;
    }
}
