<?php

declare(strict_types=1);

namespace App\Integration\Matomo;

use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

class MatomoAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
    ) {
    }

    public function doHandle(RunContext $context): void
    {
    }
}
