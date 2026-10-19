<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Service\Storage\RenditionManager;

abstract class AbstractRekognitionAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly RekognitionAnalyzer $analyzer,
        protected readonly BatchAttributeManager $batchAttributeManager,
        protected readonly RenditionManager $renditionManager,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);

        $category = $this->getCategory();
        $rendition = $config[$category]['rendition'] ?? null;

        if (null !== $rendition) {
            $file = $this->renditionManager->getAssetRenditionByName($asset->getId(), $rendition)?->getFile();
            if (null === $file) {
                throw new \InvalidArgumentException(sprintf('Rendition "%s" not found for asset "%s". Ensure the rendition creation job was run before!', $rendition, $asset->getId()));
            }
        } else {
            $file = $asset->getSource();
        }

        $this->analyzer->analyze($asset, $file, $category, $config);
    }

    abstract protected function getCategory(): string;

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
