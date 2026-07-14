<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AssetExportProcess
{
    public function __construct(
        public string $id,
    ) {
    }
}
