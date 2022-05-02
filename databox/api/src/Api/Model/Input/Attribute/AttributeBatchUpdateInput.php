<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use Symfony\Component\Validator\Constraints as Assert;

class AttributeBatchUpdateInput extends AssetAttributeBatchUpdateInput
{
    /**
     * Asset IDs.
     *
     * @var string[]
     * @Assert\NotNull()
     */
    public ?array $assets = [];
}
