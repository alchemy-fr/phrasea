<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Template;

use App\Api\Model\Input\AbstractOwnerIdInput;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;

class AssetDataTemplateInput extends AbstractOwnerIdInput
{
    public ?string $name = null;

    public ?bool $public = null;
    public ?int $privacy = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    public $workspace;

    /**
     * @var Collection
     */
    public $collection;

    public bool $includeCollectionChildren = false;

    /**
     * @var TemplateAttributeInput[]
     */
    public ?array $attributes = null;
}
