<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Similarity;

use App\Entity\Core\Asset;
use App\Service\Vector\AssetEmbeddingManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SimilarityEmbedHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AssetEmbeddingManager $assetEmbeddingManager,
    ) {
    }

    public function __invoke(SimilarityEmbed $message): void
    {
        $asset = $this->em->find(Asset::class, $message->getAssetId());
        if (!$asset instanceof Asset) {
            return;
        }

        $this->assetEmbeddingManager->embedAsset($asset, $message->getRenditionName());
    }
}
