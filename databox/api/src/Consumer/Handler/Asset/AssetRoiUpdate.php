<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * The region of interest of an asset changed: the baskets holding it are synchronized again.
 */
#[MessengerMessage('p2')]
final readonly class AssetRoiUpdate
{
    public function __construct(
        public string $assetId,
    ) {
    }
}
