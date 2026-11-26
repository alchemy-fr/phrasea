<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class NotifyAssetQuarantined
{
    public function __construct(
        private string $assetId,
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }
}
