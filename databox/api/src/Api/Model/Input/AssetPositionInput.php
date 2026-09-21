<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AssetPositionInput
{
    /**
     * Collection IRI or story Asset IRI.
     */
    #[Assert\NotNull]
    public ?string $destination = null;

    /**
     * Zero-based rank to move the asset to, clamped to the size of the destination.
     */
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    public ?int $position = null;
}
