<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Model\Step;

interface ExecutorInterface
{
    public function support(string $name): bool;

    public function execute(Step $step, RunContext $context): void;
}
