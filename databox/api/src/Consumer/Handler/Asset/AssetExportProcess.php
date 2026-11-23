<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('heavy')]
final readonly class AssetExportProcess
{
    public function __construct(
        public string $id,
    ) {
    }
}
