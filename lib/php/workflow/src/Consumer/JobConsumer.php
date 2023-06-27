<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Consumer;

use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\WorkflowOrchestrator;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessageHandlerInterface;

class JobConsumer implements EventMessageHandlerInterface
{
    final public const EVENT = 'alchemy_workflow_job_run';

    public function __construct(private readonly PlanExecutor $planExecutor, private readonly WorkflowOrchestrator $orchestrator)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->planExecutor->executePlan($payload['w'], $payload['j']);

        $this->orchestrator->continueWorkflow($payload['w']);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $workflowId, string $jobId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'w' => $workflowId,
            'j' => $jobId,
        ]);
    }

    public static function getQueueName(): string
    {
        return 'workflow';
    }

    public static function getDefaultPriority(): ?int
    {
        return null;
    }

    public function preHandle(): void
    {
    }

    public function postHandle(): void
    {
    }
}
