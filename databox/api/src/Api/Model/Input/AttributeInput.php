<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Input\Attribute\AbstractAttributeInput;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;

class AttributeInput extends AbstractAttributeInput
{
    /**
     * @var Asset
     */
    public $asset;

    /**
     * Target definition by name. Or use $definition.
     *
     * @var string|null
     */
    public $name;

    /**
     * Target definition by IRI. Or use $name.
     *
     * @var AttributeDefinition|null
     */
    public $definition;
}
