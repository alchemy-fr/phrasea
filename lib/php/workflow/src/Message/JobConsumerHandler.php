<?php

namespace Alchemy\Workflow\Message;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Executor\JobStateManager;
use Alchemy\Workflow\Runner\RunnerInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobConsumerHandler
{
    public function __construct(
        private RunnerInterface $runner,
        private WorkflowOrchestrator $orchestrator,
        private JobStateManager $jobStateManager,
    ) {
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
                    throw new \RuntimeException(sprintf('Job state "%s" not found after retry', $jobStateId));
                }

                $state->setStatus(JobState::STATUS_FAILURE);
                $state->setEndedAt(new MicroDateTime());
                $state->addError('Job timeout error');
                $this->jobStateManager->persistJobState($state);
            });

            $this->jobStateManager->flushEvents();

            return;
        }

        $this->runner->run(new JobTrigger(
            $workflowId,
            $message->getJobId(),
            $jobStateId,
        ));

        $this->orchestrator->continueWorkflow($workflowId);
    }
}
