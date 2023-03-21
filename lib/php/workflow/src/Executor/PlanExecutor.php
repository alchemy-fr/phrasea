<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\JobState;
use Throwable;

final class PlanExecutor
{
    private JobExecutor $jobExecutor;

    public function __construct(JobExecutor $jobExecutor)
    {
        $this->jobExecutor = $jobExecutor;
    }

    public function executePlan(WorkflowExecutionContext $workflowContext, ?string $triggerJobId = null): void
    {
        $plan = $workflowContext->getPlan();

        foreach ($plan->getStages() as $stage) {
            foreach ($stage->getRuns() as $run) {
                $job = $run->getJob();
                if (null === $triggerJobId || $job->getId() === $triggerJobId) {
                    $this->executeJob($workflowContext, $job);

                    return;
                }
            }
        }
    }

    private function executeJob(WorkflowExecutionContext $workflowContext, Job $job): void
    {
        $workflowContext->setJobTriggered($job->getId());
        $workflowState = $workflowContext->getState();

        $jobResultList = $workflowState->getJobResults();

        if ($job->getIf()) {
            $jobResultList->setJobState($job->getId(), JobState::STATE_SKIPPED);

            return;
        }

        $jobContext = new JobExecutionContext($workflowContext);

        try {
            $this->jobExecutor->executeJob($jobContext, $job);
            $jobResultList->setJobState($job->getId(), JobState::STATE_SUCCESS);
        } catch (Throwable $e) {
            $jobResultList->setJobState($job->getId(), JobState::STATE_FAILURE);
        }
    }
}
