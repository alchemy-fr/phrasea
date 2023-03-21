<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Executors;

use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Executor\ExecutionOutput;
use Alchemy\Workflow\Executor\ExecutorInterface;
use Alchemy\Workflow\Model\Step;

class PhpExecutor implements ExecutorInterface
{
    public function support(string $name): bool
    {
        return 'php' === $name;
    }

    public function execute(Step $step, RunContext $context, ExecutionOutput $output): void
    {
        $output->write(eval($step->getRun()));
    }
}
