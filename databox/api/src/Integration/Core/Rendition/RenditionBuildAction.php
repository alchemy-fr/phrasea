<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\RenditionBuilder;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Notification\IntegrationNotifyableException;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const string JOB_ID = 'build';

    public function __construct(
        private readonly RenditionBuilder $renditionBuilder,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $force = $context->getInputs()['rerun'] ?? false;
        $asset = $this->getAsset($context);
        $inputs = $context->getInputs();
        $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $inputs['definition']);

        try {
            $this->renditionBuilder->buildRendition($renditionDefinition, $asset, $force);
        } catch (\Throwable $e) {
            $this->handleException($e, $context);
        }
    }
}
