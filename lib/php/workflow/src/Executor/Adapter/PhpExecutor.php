<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Adapter;

use Alchemy\Workflow\Executor\ExecutorInterface;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Model\Step;
use Symfony\Component\Process\PhpProcess;

class PhpExecutor implements ExecutorInterface
{
    public function support(string $name): bool
    {
        return 'php' === $name;
    }

    public function execute(Step $step, RunContext $context): void
    {
        $output = $context->getOutput();

        if ($output->isVerbose()) {
            $output->writeln(sprintf('+ <info>%s</info>', trim($step->getRun())));
        }

        $process = new PhpProcess(sprintf('<?php
%s
?>', $step->getRun()));
        $process->run();

        $context->getOutput()->write($process->getOutput());
    }
}
