<?php

namespace Alchemy\Workflow\Message;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Exception\WorkflowStateNotFoundException;
use Alchemy\Workflow\Executor\JobStateManager;
use Alchemy\Workflow\Runner\RunnerInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\WorkflowOrchestrator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobConsumerHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private RunnerInterface $runner,
        private WorkflowOrchestrator $orchestrator,
        private JobStateManager $jobStateManager,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(JobConsumer $message, int $retryCount): void
    {
        $workflowId = $message->getWorkflowId();
        $jobStateId = $message->getJobStateId();

        if ($retryCount > 0) {
            // Messenger retry logic: mark the job as failed as it should have been interrupted
            $this->jobStateManager->wrapInTransaction(function () use ($workflowId, $jobStateId): void {
                $this->jobStateManager->acquireJobLock($workflowId, $jobStateId);
                $state = $this->jobStateManager->getJobState($workflowId, $jobStateId);
                if (null === $state) {
                    // The workflow (and its job states) was removed meanwhile: nothing to mark as failed.
                    $this->logger->warning(sprintf('Job state "%s" of workflow "%s" not found after retry, skipping', $jobStateId, $workflowId));

                    return;
                }

                $state->setStatus(JobState::STATUS_FAILURE);
                $state->setEndedAt(new MicroDateTime());
                $state->addError('Job timeout error');
                $this->jobStateManager->persistJobState($state);
            });

            $this->jobStateManager->flushEvents();

            return;
        }

        try {
            $this->runner->run(new JobTrigger(
                $workflowId,
                $message->getJobId(),
                $jobStateId,
            ));

            $this->orchestrator->continueWorkflow($workflowId);
        } catch (WorkflowStateNotFoundException $e) {
            // Workflow states are cascade-deleted with their subject (e.g. asset removal)
            // while job messages may still be queued: retrying cannot help.
            $this->logger->warning(sprintf('Workflow "%s" no longer exists, skipping job "%s": %s', $workflowId, $message->getJobId(), $e->getMessage()));
        }
    }
}
