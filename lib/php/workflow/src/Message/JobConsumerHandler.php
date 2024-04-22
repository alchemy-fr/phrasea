<?php

namespace Alchemy\Workflow\Message;

use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobConsumerHandler
{
    public function __construct(
        private PlanExecutor $planExecutor,
        private WorkflowOrchestrator $orchestrator
    )
    {
    }

    public function __invoke(JobConsumer $message): void
    {
        $this->planExecutor->executePlan($message->getWorkflowId(), $message->getJobId());
        $this->orchestrator->continueWorkflow($message->getWorkflowId());
    }
}
