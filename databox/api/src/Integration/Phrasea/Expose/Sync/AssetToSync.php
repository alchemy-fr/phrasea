<?php

namespace App\Integration\Phrasea\Expose\Sync;

use App\Entity\Basket\BasketAsset;

final readonly class AssetToSync
{
    public function __construct(
        public BasketAsset $basketAsset,
        public ?ExposeAsset $exposeAsset = null,
    ) {
    }
}
