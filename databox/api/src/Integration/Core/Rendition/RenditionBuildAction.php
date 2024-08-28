<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class RenditionBuildAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const JOB_ID = 'build';

    public function handle(RunContext $context): void
    {
        // TODO
    }
}
