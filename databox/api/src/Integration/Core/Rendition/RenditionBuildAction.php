<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Storage\FileManager;
use App\Storage\RenditionManager;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const JOB_ID = 'build';

    public function __construct(
        private readonly RenditionManager $renditionManager,
        private readonly YamlLoader $loader,
        private readonly FileFetcher $fileFetcher,
        private readonly FileManager $fileManager,
        private readonly RenditionCreator $renditionCreator,
        private readonly AttributesResolver $attributesResolver,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $force = $context->getInputs()['rerun'] ?? false;
        $asset = $this->getAsset($context);
        $inputs = $context->getInputs();
        $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $inputs['definition']);

        if ($renditionDefinition->isPickSourceFile()) {
            $this->renditionManager->createOrReplaceRenditionFile($asset, $renditionDefinition, $asset->getSource(), null);
            $this->em->flush();

            return;
        }

        if (null !== $parentDefinition = $renditionDefinition->getParent()) {
            $parentRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $parentDefinition);
            if (null === $parentRendition) {
                throw new \LogicException(sprintf('Parent rendition "%s" not found for asset "%s"', $parentDefinition->getName(), $asset->getId()));
            }

            $source = $parentRendition->getFile();
        } else {
            $source = $asset->getSource();
        }

        $buildDef = $renditionDefinition->getDefinition();
        $buildHash = md5(implode('|', [
            $source->getId(),
            $renditionDefinition->getId(),
            $buildDef,
        ]));

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $renditionDefinition);
        if (!$force && $existingRendition?->getBuildHash() === $buildHash) {
            return;
        }

        $outputFile = $this->createRendition($asset, $source, $buildDef);

        $file = $this->fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $outputFile->getPath(),
            $outputFile->getType()
        );

        $this->renditionManager->createOrReplaceRenditionFile($asset, $renditionDefinition, $file, $buildHash);
        $this->em->flush();
    }

    private function createRendition(Asset $asset, File $source, string $buildDef): OutputFileInterface
    {
        return $this->renditionCreator->createRendition(
            $this->fileFetcher->getFile($source),
            $source->getType(),
            $this->loader->parse($buildDef),
            new CreateRenditionOptions(
                metadataContainer: new AssetMetadataContainer($asset, $this->attributesResolver),
            )
        );
    }
}
