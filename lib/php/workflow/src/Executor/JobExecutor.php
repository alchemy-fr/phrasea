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
        foreach ($job->getSteps() as $step) {
            $executorName = $step->getExecutor();

            $output = new ExecutionOutput();
            $runContext = new RunContext();

            foreach ($this->executors as $executor) {
                if ($executor->support($executorName)) {
                    $executor->execute($step, $runContext, $output);

                    break;
                }
            }

            $context->getWorkflowContext()->continueWorkflow();
        }
    }
}
