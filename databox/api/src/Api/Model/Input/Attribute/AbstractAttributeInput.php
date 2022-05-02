<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

abstract class AbstractAttributeInput
{
    /**
     * @var string|float|int|bool|array|null
     */
    public $value;

    /**
     * "human" or "machine".
     *
     * @var string|null
     */
    public $origin;

    /**
     * @var string|null
     */
    public $locale;

    /**
     * @var int|null
     */
    public $position;

    public ?string $originVendor = null;

    public ?string $originUserId = null;

    public ?string $originVendorContext = null;

    /**
     * @var array|string
     */
    public $coordinates = null;

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
