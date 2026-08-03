<?php

declare(strict_types=1);

namespace App\Integration\Core\Similarity;

use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Service\Vector\AssetEmbeddingManager;

class SimilarityEmbedAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly AssetEmbeddingManager $assetEmbeddingManager,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $force = $context->getInputs()['rerun'] ?? false;

        $this->assetEmbeddingManager->embedAsset($asset, $config['rendition'], $force);
    }

    #[\Override]
    protected function shouldRun(Asset $asset): bool
    {
        return null !== $asset->getSource();
    }
}
