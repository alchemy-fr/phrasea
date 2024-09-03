<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Storage\RenditionManager;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const JOB_ID = 'build';

    public function __construct(
        private readonly RenditionManager $renditionManager,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $inputs = $context->getInputs();
        $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $inputs['definition']);

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

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $parentDefinition->getName());

        if ($existingRendition->getBuildHash() === $buildHash) {
            return;
        }

        // TODO build $buildDef
    }
}
