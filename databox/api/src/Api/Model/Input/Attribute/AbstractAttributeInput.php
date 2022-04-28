<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Entity\Core\Attribute;

abstract class AbstractAttributeInput
{
    /**
     * @var string|float|int|bool|array|null
     */
    public $value;

    /**
     * "human" or "machine".
     *
     * @var string
     */
    public $origin = Attribute::ORIGIN_LABELS[Attribute::ORIGIN_MACHINE];

    /**
     * @var string
     */
    public $locale;

    /**
     * @var int
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
