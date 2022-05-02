<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

class AssetAttributeBatchUpdateInput
{
    /**
     * @var AttributeActionInput[]
     */
    public ?array $actions = null;
}
