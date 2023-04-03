<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Exception\ConcurrencyException;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class PlanExecutor
{
    private WorkflowRepositoryInterface $workflowRepository;
    private StateRepositoryInterface $stateRepository;
    private JobExecutor $jobExecutor;
    private OutputInterface $output;

    public function __construct(
        WorkflowRepositoryInterface $workflowRepository,
        StateRepositoryInterface $stateRepository,
        JobExecutor $jobExecutor,
        OutputInterface $output = null
    )
    {
        $this->workflowRepository = $workflowRepository;
        $this->stateRepository = $stateRepository;
        $this->jobExecutor = $jobExecutor;
        $this->output = $output ?? new NullOutput();
    }

    public function executePlan(string $workflowId, string $jobId): void
    {
        $workflowState = $this->stateRepository->getWorkflowState($workflowId);

        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->acquireJobLock($workflowId, $jobId);
        }

        $jobState = $this->stateRepository->getJobState($workflowId, $jobId);

        if (JobState::STATUS_TRIGGERED !== $jobState->getStatus()) {
            throw new ConcurrencyException(sprintf('Job "%s" has not the TRIGGERED status for workflow "%s"', $jobId, $workflowId));
        }

        $context = new JobExecutionContext(
            $workflowState,
            $jobState,
            $this->output
        );

        $job = $plan->getJob($jobId);

        if (null === $jobState) {
            throw new \InvalidArgumentException(sprintf('State of job "%s" does not exists for workflow "%s"', $jobId, $workflowId));
        }

        $this->executeJob($context, $job);

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->releaseJobLock($workflowId, $jobId);
        }
    }

    public function executeJob(JobExecutionContext $jobExecutionContext, Job $job): void
    {
        $jobState = $jobExecutionContext->getJobState();

        try {
            $jobState->setStatus(JobState::STATUS_RUNNING);
            $jobState->setStartedAt(new \DateTimeImmutable());
            $this->stateRepository->persistJobState($jobState);

            $this->jobExecutor->executeJob($jobExecutionContext, $job);

            $jobState->setEndedAt(new \DateTimeImmutable());
            $jobState->setStatus(JobState::STATUS_SUCCESS);
            $this->stateRepository->persistJobState($jobState);
        } catch (\Throwable $e) {
            $jobState->setEndedAt(new \DateTimeImmutable());
            $jobState->setStatus(JobState::STATUS_FAILURE);
            $this->stateRepository->persistJobState($jobState);
        }
    }
}
