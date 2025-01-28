<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class IndexAssetAttributes
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
