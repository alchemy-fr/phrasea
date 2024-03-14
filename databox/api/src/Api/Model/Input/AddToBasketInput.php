<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AddToBasketInput
{
    /**
     * @var AssetToBasketInput[]|string[]
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?array $assets = null;
}
