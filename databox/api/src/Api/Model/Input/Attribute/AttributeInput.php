<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Entity\Core\Asset;

class AttributeInput extends AbstractExtendedAttributeInput
{
    /**
     * @var Asset
     */
    public $asset;
}
