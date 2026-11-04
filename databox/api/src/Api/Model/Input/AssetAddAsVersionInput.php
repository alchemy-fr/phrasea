<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AssetAddAsVersionInput
{
    /**
     * ID of the existing (duplicate) asset the quarantined source file should
     * be attached to as a new source version.
     */
    #[Assert\NotBlank]
    public ?string $targetAssetId = null;
}
