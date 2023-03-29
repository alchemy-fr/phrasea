<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Consumer;

use Alchemy\Workflow\Executor\PlanExecutor;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessageHandlerInterface;

class JobConsumer implements EventMessageHandlerInterface
{
    const EVENT = 'alchemy_workflow_job_run';
    private PlanExecutor $planExecutor;

    public function __construct(PlanExecutor $planExecutor)
    {
        $this->planExecutor = $planExecutor;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->planExecutor->executePlan($payload['w'], $payload['j']);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $workflowId, string $jobId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'w' => $workflowId, $jobId,
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
