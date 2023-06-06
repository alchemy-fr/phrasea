<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Util\FileUtil;

class RemoveBgAction extends AbstractIntegrationAction
{
    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $this->removeBgProcessor->process($asset->getSource(), $config);
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }
}
