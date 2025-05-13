<?php

namespace Alchemy\Workflow\Message;

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
        if ($retryCount > 0) {
            // Messenger retry logic: mark the job as failed as it should have been interrupted
            $this->jobStateManager->acquireJobLock($message->getWorkflowId(), $message->getJobStateId());
            $state = $this->jobStateManager->getJobState($message->getWorkflowId(), $message->getJobStateId());
            if (null === $state) {
                throw new \RuntimeException(sprintf('Job state "%s" not found after retry', $message->getJobStateId()));
            }

            $state->setStatus(JobState::STATUS_FAILURE);
            $state->addError('Job timeout error');
            $this->jobStateManager->persistJobState($state);

            return;
        }

        $this->runner->run(new JobTrigger(
            $message->getWorkflowId(),
            $message->getJobId(),
            $message->getJobStateId(),
        ));

        $this->orchestrator->continueWorkflow($message->getWorkflowId());
    }
}
