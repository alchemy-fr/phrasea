<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

abstract class AbstractExtendedAttributeInput extends AbstractBaseAttributeInput
{
    /**
     * "human" or "machine".
     *
     * @var string|null
     */
    public $origin;

    public ?string $originVendor = null;

    public ?string $originUserId = null;

    public ?string $originVendorContext = null;

    /**
     * @var array|string
     */
    public $coordinates;

    /**
     * "valid" | "review_pending" | "declined".
     *
     * @var string
     */
    public $status;

    /**
     * @var float
     */
    public $confidence;
}
