<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Similarity;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class SimilarityEmbed
{
    public function __construct(
        private string $assetId,
        private string $renditionName,
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getRenditionName(): string
    {
        return $this->renditionName;
    }
}
