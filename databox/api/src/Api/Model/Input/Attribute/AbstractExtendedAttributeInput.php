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
     * Shapes drawn over the asset for this value (see AssetAnnotationsInterface).
     * Null leaves the stored annotations untouched, an empty list removes them.
     *
     * @var array|null
     */
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'type' => new Assert\Choice(choices: AssetAnnotationsInterface::TYPES),
            ],
            allowExtraFields: true,
        ),
    ])]
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

    /**
     * Signature of the annotated region ({m: model, v: float[]}), kept with the value but never exposed.
     *
     * @var array{m: string, v: float[]}|null
     */
    public ?array $embedding = null;
}
