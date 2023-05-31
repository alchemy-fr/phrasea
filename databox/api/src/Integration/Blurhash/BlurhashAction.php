<?php

declare(strict_types=1);

namespace App\Integration\Blurhash;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\FileFetcher;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Util\FileUtil;
use kornrunner\Blurhash\Blurhash;

class BlurhashAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly FileFetcher $fileFetcher,
        private readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);

        $input = new AssetAttributeBatchUpdateInput();
        $i = new AttributeActionInput();
        $i->originVendor = BlurhashIntegration::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.BlurhashIntegration::VERSION;
        $i->name = 'blurhash';
        $i->value = $this->getBlurhash($asset->getSource());
        $input->actions[] = $i;

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $input,
            null
        );
    }

    private function getBlurhash(File $file): string
    {
        $file = $this->fileFetcher->getFile($file);
        $image = imagecreatefromstring(file_get_contents($file));
        $width = imagesx($image);
        $height = imagesy($image);

        $pixels = [];
        for ($y = 0; $y < $height; ++$y) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $index = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $index);

                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
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
