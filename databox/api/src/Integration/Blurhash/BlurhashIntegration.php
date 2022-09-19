<?php

declare(strict_types=1);

namespace App\Integration\Blurhash;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\FileFetcher;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Integration\AbstractIntegration;
use App\Integration\AssetOperationIntegrationInterface;
use App\Util\FileUtil;
use kornrunner\Blurhash\Blurhash;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlurhashIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface
{
    private const VERSION = '1.0';
    private FileFetcher $fileFetcher;
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(FileFetcher $fileFetcher, BatchAttributeManager $batchAttributeManager)
    {
        $this->fileFetcher = $fileFetcher;
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        $input = new AssetAttributeBatchUpdateInput();
        $i = new AttributeActionInput();
        $i->originVendor = self::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.self::VERSION;
        $i->name = 'blurhash';
        $i->value = $this->getBlurhash($asset->getFile());
        $input->actions[] = $i;

        $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input);
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

    public static function getName(): string
    {
        return 'blurhash';
    }

    public function supportsAsset(Asset $asset, array $options): bool
    {
        return $asset->getFile() && FileUtil::isImageType($asset->getFile()->getType());
    }

    public static function getTitle(): string
    {
        return 'Blurhash';
    }
}
