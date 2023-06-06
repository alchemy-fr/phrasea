<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\JobExecutionContext;

interface IfActionInterface extends ActionInterface
{
    public function evaluateIf(JobExecutionContext $context): bool;
}
