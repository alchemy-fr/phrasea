<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\Workflow\Executor\RunContext;
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
    )
    {
    }

    public function handle(RunContext $context): void
    {
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
        if ($existingRendition?->getBuildHash() === $buildHash) {
            return;
        }

        $outputFile = $this->createRendition($source, $buildDef);

        $file = $this->fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $outputFile->getPath(),
            $outputFile->getType()
        );

        $this->renditionManager->createOrReplaceRenditionFile($asset, $renditionDefinition, $file, $buildHash);
        $this->em->flush();
    }

    private function createRendition(File $source, string $buildDef): OutputFile
    {
        $buildConfig = $this->loader->parse($buildDef);

        $localPath = $this->fileFetcher->getFile($source);

        return $this->renditionCreator->createRendition(
            $localPath,
            $source->getType(),
            $buildConfig,
        );
    }
}
