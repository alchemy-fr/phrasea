<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\RenditionBuilder;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const JOB_ID = 'build';

    public function __construct(
        private readonly RenditionBuilder $renditionBuilder,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $force = $context->getInputs()['rerun'] ?? false;
        $asset = $this->getAsset($context);
        $inputs = $context->getInputs();
        $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $inputs['definition']);

        $this->renditionBuilder->buildRendition($renditionDefinition, $asset, $force);
    }
}
