<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AssetToBasketInput
{
    #[Assert\NotNull]
    public ?string $id = null;

    /**
     * Optional time ranges and/or crop of the created item.
     */
    #[Assert\Valid]
    public ?MediaClipInput $clip = null;
}
