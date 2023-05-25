<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Util\FileUtil;

class RemoveBgAction extends AbstractIntegrationAction implements IfActionInterface
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

    public function shouldRun(JobContext $context): bool
    {
        $asset = $this->getAsset($context);
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }
}
