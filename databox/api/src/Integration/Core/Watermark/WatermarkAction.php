<?php

declare(strict_types=1);

namespace App\Integration\Core\Watermark;

use Alchemy\Workflow\Executor\RunContext;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\FileFetcher;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Image\ImageManagerFactory;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Storage\FileManager;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\Imagick\Font;

class WatermarkAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly FileFetcher $fileFetcher,
        private readonly AttributesResolver $attributesResolver,
        private readonly ImageManagerFactory $imageManagerFactory,
        private readonly RenditionManager $renditionManager,
        private readonly AttributeManager $attributeManager,
        private readonly FileManager $fileManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $manager = $this->imageManagerFactory->createManager();

        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);
        $attrName = $config['attributeName'];

        $attrDef = $this->attributeManager->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $attrName)
            ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $attrName, $asset->getWorkspaceId()));

        $text = $attributeIndex->getAttribute($attrDef->getId(), AttributeInterface::NO_LOCALE)?->getValue();

        if (empty($text)) {
            return;
        }

        $top = $config['position']['top'];
        $left = $config['position']['left'];
        $fontSize = (int) str_replace('px', '', (string) $config['fontSize']);
        $color = preg_replace('/^#/', '', (string) $config['color']);

        foreach ($config['applyToRenditions'] as $renditionName) {
            $rendition = $this->getRendition($asset->getId(), $renditionName);
            $file = $rendition->getFile();
            $src = $this->fileFetcher->getFile($file);

            $image = $manager->make($src);
            $x = $this->resolvePosition($left, $image->width());
            $y = $this->resolvePosition($top, $image->height());

            $image->text($text, $x, $y, function (Font $font) use ($fontSize, $color): void {
                $font->file(__DIR__.'/Roboto-Regular.ttf');
                $font->size($fontSize);
                $font->color($color);
                $font->align('center');
                $font->valign('center');
            });

            $path = tempnam(sys_get_temp_dir(), self::class);
            $image->save($path);
            unlink($src);

            $newRenditionFile = $this->fileManager->createFileFromPath(
                $asset->getWorkspace(),
                $path,
                $file->getType(),
                $file->getExtension(),
                $file->getOriginalName(),
            );

            $this->renditionManager->createOrReplaceRenditionFile(
                $asset,
                $rendition->getDefinition(),
                $newRenditionFile
            );
        }

        $this->em->flush();
    }

    private function resolvePosition(string $pos, int $size): int
    {
        if (str_ends_with($pos, '%')) {
            $pos = str_replace('%', '', $pos);

            return (int) round($size * $pos * 0.01);
        }

        return (int) $pos;
    }

    private function getRendition(string $assetId, string $renditionName): AssetRendition
    {
        return $this->renditionManager->getAssetRenditionByName($assetId, $renditionName)
            ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" does not exist for asset "%s"', $renditionName, $assetId));
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
