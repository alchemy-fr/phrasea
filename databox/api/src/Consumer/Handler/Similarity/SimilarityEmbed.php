<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Similarity;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Service\Vector\AssetEmbeddingManager;

#[MessengerMessage('p2')]
final readonly class SimilarityEmbed
{
    public function __construct(
        private string $assetId,
        private string $renditionName = AssetEmbeddingManager::DEFAULT_RENDITION,
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
