<?php

declare(strict_types=1);

namespace App\Integration\Blurhash;

use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\FileFetcher;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Image\ImageManagerFactory;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Storage\RenditionManager;
use App\Util\FileUtil;
use kornrunner\Blurhash\Blurhash;

class BlurhashAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly FileFetcher $fileFetcher,
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly ImageManagerFactory $imageManagerFactory,
        private readonly RenditionManager $renditionManager,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $input = new AssetAttributeBatchUpdateInput();
        $i = new AttributeActionInput();
        $i->originVendor = BlurhashIntegration::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.BlurhashIntegration::VERSION;
        $i->name = $config['attribute'];

        $file = !empty($config['rendition']) ? $this->getRenditionFile($asset->getId(), $config['rendition']) : $asset->getSource();

        $i->value = $this->getBlurhash($file);
        $input->actions[] = $i;

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $input,
            null
        );
    }

    private function getRenditionFile(string $assetId, string $renditionName): File
    {
        $rendition = $this->renditionManager->getAssetRenditionByName($assetId, $renditionName)
            ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" does not exist for asset "%s"', $renditionName, $assetId));

        return $rendition->getFile();
    }

    private function getBlurhash(File $file): string
    {
        $file = $this->fileFetcher->getFile($file);
        $manager = $this->imageManagerFactory->createManager();

        $image = $manager->make($file);
        $width = $image->width();
        $height = $image->height();

        $maxSize = 100;
        if( $width > $maxSize || $height > $maxSize) {
            $image->resize($maxSize, $maxSize, function ($constraint) {
                $constraint->aspectRatio();
            });
            $width = $image->width();
            $height = $image->height();
        }

        $pixels = [];
        for ($y = 0; $y < $height; ++$y) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $colors = $image->pickColor($x, $y);

                $row[] = [$colors[0], $colors[1], $colors[2]];
            }
            $pixels[] = $row;
        }

        $components_x = 4;
        $components_y = 3;

        return Blurhash::encode($pixels, $components_x, $components_y);
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }
}
