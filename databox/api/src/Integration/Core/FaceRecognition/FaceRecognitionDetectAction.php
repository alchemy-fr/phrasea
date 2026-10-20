<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class FaceRecognitionDetectAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly FaceRecognitionAnalyzer $analyzer,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $this->analyzer->analyze($asset, $config);
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
