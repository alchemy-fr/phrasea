<?php

namespace Alchemy\Workflow\Message;

use Alchemy\Workflow\Runner\RunnerInterface;
use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobConsumerHandler
{
    public function __construct(
        private RunnerInterface $runner,
        private WorkflowOrchestrator $orchestrator,
    ) {
    }

    public function __invoke(JobConsumer $message): void
    {
        $this->runner->run(new JobTrigger(
            $message->getWorkflowId(),
            $message->getJobId(),
            $message->getJobStateId(),
        ));
        $this->orchestrator->continueWorkflow($message->getWorkflowId());
    }
}
