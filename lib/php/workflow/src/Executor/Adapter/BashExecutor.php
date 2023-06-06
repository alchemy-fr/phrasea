<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Adapter;

use Alchemy\Workflow\Executor\ExecutorInterface;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Model\Step;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BashExecutor implements ExecutorInterface
{
    public function support(string $name): bool
    {
        return 'bash' === $name;
    }

    public function execute(string $run, RunContext $context): void
    {
        $output = $context->getOutput();

        if ($output->isVerbose()) {
            $output->writeln(sprintf('+ <info>%s</info>', trim($run)));
        }

        $process = Process::fromShellCommandline($run, null, ['ENV_VAR_NAME' => 'value']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output->write($process->getOutput());
    }
}
