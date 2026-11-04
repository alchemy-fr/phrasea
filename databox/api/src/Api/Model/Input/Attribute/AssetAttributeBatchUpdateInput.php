<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use Symfony\Component\Validator\Constraints as Assert;

class AssetAttributeBatchUpdateInput
{
    /**
     * @var AttributeActionInput[]
     */
    #[Assert\Valid]
    public ?array $actions = null;
}
