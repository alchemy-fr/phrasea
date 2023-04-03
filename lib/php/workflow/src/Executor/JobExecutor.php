<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Model\Job;

class JobExecutor
{
    /**
     * @var ExecutorInterface[]
     */
    private iterable $executors;

    public function __construct(iterable $executors)
    {
        $this->executors = $executors;
    }

    public function executeJob(JobExecutionContext $context, Job $job): void
    {
        $output = $context->getOutput();
        $output->writeln(sprintf('Running job <info>%s</info>', $job->getId()));

        foreach ($job->getSteps() as $step) {
            try {
                $executorName = $step->getExecutor();
                $output->writeln(sprintf('Running step <info>%s</info>', $step->getId()));

                $runContext = new RunContext($output);

                foreach ($this->executors as $executor) {
                    if ($executor->support($executorName)) {
                        $executor->execute($step, $runContext);

                        break;
                    }
                }
            } catch (\Throwable $e) {
                if (!$step->isContinueOnError()) {
                    throw $e;
                }
                $output->writeln(sprintf('<error>Step <info>%s</info> has failed but is set to continue on error</error>', $step->getId()));
            }
        }
    }
}
