<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Util\FileUtil;

abstract class AbstractRekognitionAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly RekognitionAnalyzer $analyzer,
        protected readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);
        $file = $asset->getSource();
        $category = $this->getCategory();
        $this->analyzer->analyze($asset, $file, $category, $config);
    }

    abstract protected function getCategory(): string;

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }
}
