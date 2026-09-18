<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Service\Asset\FileFetcher;
use App\Service\Asset\RenditionBuilder;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const string JOB_ID = 'build';

    public function __construct(
        private readonly RenditionBuilder $renditionBuilder,
        private readonly FileFetcher $fileFetcher,
    ) {
    }

    #[\Override]
    protected function shouldRun(Asset $asset): bool
    {
        $source = $asset->getSource();
        if (null === $source) {
            return false;
        }

        // Renditions are derived from the source content: a private remote source
        // (FileSourceInput::isPrivate) cannot be downloaded, skip the job.
        return $this->fileFetcher->isFetchable($source);
    }

    public function doHandle(RunContext $context): void
    {
        $force = $context->getInputs()['rerun'] ?? false;
        $asset = $this->getAsset($context);
        $inputs = $context->getInputs();
        $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $inputs['definition']);

        $this->renditionBuilder->buildRendition($renditionDefinition, $asset, $force);
    }
}
