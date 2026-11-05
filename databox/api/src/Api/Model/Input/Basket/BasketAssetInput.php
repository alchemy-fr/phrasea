<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Basket;

use App\Api\Model\Input\MediaClipInput;
use Symfony\Component\Validator\Constraints as Assert;

class BasketAssetInput
{
    /**
     * Time ranges and/or crop of the item, in place of the region of interest of its asset:
     * `null` or `{}` remove it, an omitted key leaves it unchanged.
     */
    #[Assert\Valid]
    public ?MediaClipInput $clip = null;
}
