<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;

class RemoveBgAction extends AbstractIntegrationAction
{
    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $this->removeBgProcessor->process($asset->getSource(), $config);
    }

    #[\Override]
    protected function getSkipReason(Asset $asset): ?string
    {
        if (null === $asset->getSource()) {
            return self::SKIP_NO_SOURCE;
        }

        $type = $asset->getSource()->getType();

        return FileUtil::isImageType($type) ? null : sprintf('Source file type "%s" is not an image', $type);
    }
}
