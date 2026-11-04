<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Basket;

use Symfony\Component\Validator\Constraints as Assert;

class BasketAssetInput
{
    /**
     * Time range and/or crop of the item: `null` or `{}` remove it, an omitted key leaves it unchanged.
     */
    #[Assert\Valid]
    public ?BasketClipInput $clip = null;
}
