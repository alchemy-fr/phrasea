<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;

class AssetStoryInput extends AbstractOwnerIdInput
{
    use ExtraMetadataDTOTrait;

    public ?string $name = null;
    /**
     * @var AttributeInput[]
     */
    public ?array $attributes = null;
}
