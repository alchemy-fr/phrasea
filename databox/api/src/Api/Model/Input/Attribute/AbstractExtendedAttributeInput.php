<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Entity\Traits\AssetAnnotationsInterface;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @var array
     */
    #[Assert\Collection(
        fields: [
            'type' => new Assert\Choice(AssetAnnotationsInterface::TYPES),
        ],
        allowExtraFields: true,
    )]
    public $annotations;

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
