<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Executors;

use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Executor\ExecutionOutput;
use Alchemy\Workflow\Executor\ExecutorInterface;
use Alchemy\Workflow\Model\Step;

class BashExecutor implements ExecutorInterface
{
    public function support(string $name): bool
    {
        return 'bash' === $name;
    }

    public function execute(Step $step, RunContext $context, ExecutionOutput $output): void
    {
        $cmdOutput = [];
        exec($step->getRun(), $cmdOutput);

        foreach ($cmdOutput as $line) {
            $output->write($line);
        }
    }
}
