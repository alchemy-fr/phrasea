<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Template;

use App\Api\Model\Output\AbstractUuidOutput;
use App\Api\Model\Output\AttributeOutput;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetDataTemplateOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[Groups([AssetDataTemplate::GROUP_LIST])]
    protected array $capabilities = [];

    /**
     * @var AttributeOutput[]
     */
    #[Groups([AssetDataTemplate::GROUP_READ])]
    public ?array $attributes = null;

    /**
     * Template name.
     */
    #[Groups([AssetDataTemplate::GROUP_LIST])]
    public ?string $name = null;

    #[Groups([AssetDataTemplate::GROUP_READ])]
    public bool $public = false;

    #[Groups([AssetDataTemplate::GROUP_READ])]
    public ?string $ownerId = null;

    /**
     * Asset title.
     */
    #[Groups([AssetDataTemplate::GROUP_READ])]
    public ?string $title = null;

    #[Groups([AssetDataTemplate::GROUP_READ])]
    public ?array $tags = null;

    #[Groups([AssetDataTemplate::GROUP_LIST])]
    public $collection;

    #[Groups([AssetDataTemplate::GROUP_LIST])]
    public ?int $privacy = null;

    #[Groups([AssetDataTemplate::GROUP_READ])]
    public bool $includeCollectionChildren = false;
}
