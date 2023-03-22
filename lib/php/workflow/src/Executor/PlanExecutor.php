<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\JobState;

final class PlanExecutor
{
    private JobExecutor $jobExecutor;

    public function __construct(JobExecutor $jobExecutor)
    {
        $this->jobExecutor = $jobExecutor;
    }

    public function executePlan(WorkflowExecutionContext $workflowContext, string $jobId): void
    {
        $plan = $workflowContext->getPlan();

        $job = $plan->getJob($jobId);
        $this->executeJob($workflowContext, $job);

        $workflowContext->continueWorkflow();
    }

    private function executeJob(WorkflowExecutionContext $workflowContext, Job $job): void
    {
        $jobContext = new JobExecutionContext($workflowContext);

        try {
            $workflowContext->updateJobState($job->getId(), JobState::STATUS_RUNNING);
            $this->jobExecutor->executeJob($jobContext, $job);
            $workflowContext->updateJobState($job->getId(), JobState::STATUS_SUCCESS);
        } catch (\Throwable $e) {
            $workflowContext->updateJobState($job->getId(), JobState::STATUS_FAILURE);

            throw $e;
        }
    }
}
