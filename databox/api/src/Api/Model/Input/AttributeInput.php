<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;

class AttributeInput
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

    /**
     * @var string
     */
    public $value;

    /**
     * @var array
     */
    public $values;

    /**
     * "human" or "machine".
     *
     * @var string
     */
    public $origin;

    /**
     * @var string
     */
    public $locale;

    public ?string $originVendor = null;

    public ?string $originUserId = null;

    public ?string $originVendorContext = null;

    public ?string $coordinates = null;

    /**
     * "valid" | "review_pending" | "declined"
     *
     * @var string
     */
    public $status;

    /**
     * @var float
     */
    public $confidence;
}
