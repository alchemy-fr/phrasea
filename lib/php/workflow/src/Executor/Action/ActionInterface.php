<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Action;

use Alchemy\Workflow\Executor\RunContext;

interface ActionInterface
{
    public function handle(RunContext $context): void;
}
