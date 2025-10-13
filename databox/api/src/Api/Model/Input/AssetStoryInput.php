<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;
use App\Entity\Core\Tag;

class AssetStoryInput extends AbstractOwnerIdInput
{
    use ExtraMetadataDTOTrait;

    public ?string $title = null;
    /**
     * @var Tag[]
     */
    public ?array $tags = null;
    /**
     * @var AttributeInput[]
     */
    public ?array $attributes = null;
}
