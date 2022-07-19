<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;

class AssetInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?string $key = null;

    public ?int $privacy = null;

    public ?string $privacyLabel = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    public $workspace = null;

    public ?Collection $collection = null;

    /**
     * @var AttributeInput[]
     */
    public ?array $attributes = null;

    /**
     * @var AssetSourceInput|null
     */
    public $source = null;

    public $generateRenditions = true;

    /**
     * @var RenditionInput[]
     */
    public ?array $renditions = null;
}
