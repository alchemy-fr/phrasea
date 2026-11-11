<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use Alchemy\Workflow\Message\JobConsumer;
use Alchemy\Workflow\Message\JobConsumerHandler;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Tests\State\TestStateStateRepository;
use Alchemy\Workflow\Tests\Trigger\QueuedJobTrigger;
use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WorkflowUpdateEventTest extends AbstractWorkflowTestCase
{
    /**
     * @var list<array{int, int}> [status, transaction depth] of each dispatched WorkflowUpdateEvent
     */
    private array $updates = [];

    private WorkflowOrchestrator $orchestrator;
    private TestStateStateRepository $stateRepository;
    private QueuedJobTrigger $trigger;
    private JobConsumerHandler $handler;

    protected function setUp(): void
    {
        $this->updates = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkflowUpdateEvent::class, function (WorkflowUpdateEvent $event): void {
            $this->updates[] = [$event->getState()->getStatus(), $this->getTransactionDepth()];
        });

        [$this->orchestrator, $this->stateRepository, , $runner, $jobStateManager, $this->trigger] = $this->createOrchestrator(
            ['echoer.yaml'],
            null,
            eventDispatcher: $eventDispatcher,
            asynchronous: true,
        );

        $this->handler = new JobConsumerHandler($runner, $this->orchestrator, $jobStateManager);
    }

    public function testWorkflowUpdateIsDispatchedOnStartAndEndOutsideOfTheTransaction(): void
    {
        $workflowState = $this->orchestrator->startWorkflow('Echo something');

        $this->assertSame([[WorkflowState::STATUS_STARTED, 0]], $this->updates);

        $this->consumeJobs();

        $this->assertSame([
            [WorkflowState::STATUS_STARTED, 0],
            [WorkflowState::STATUS_SUCCESS, 0],
        ], $this->updates);
        $this->assertSame(WorkflowState::STATUS_SUCCESS, $this->stateRepository->getWorkflowState($workflowState->getId())->getStatus());
    }

    public function testSynchronousWorkflowIsNotifiedOnceWithItsFinalStatus(): void
    {
        $updates = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkflowUpdateEvent::class, function (WorkflowUpdateEvent $event) use (&$updates): void {
            $updates[] = $event->getState()->getStatus();
        });
        [$orchestrator] = $this->createOrchestrator(['echoer.yaml'], null, eventDispatcher: $eventDispatcher);

        $orchestrator->startWorkflow('Echo something');

        $this->assertSame([WorkflowState::STATUS_SUCCESS], $updates);
    }

    public function testRetriedJobEndsTheWorkflow(): void
    {
        $workflowState = $this->orchestrator->startWorkflow('Echo something');
        $intro = $this->trigger->shift();
        $this->assertSame('intro', $intro->getJobId());

        ($this->handler)(new JobConsumer($intro->getWorkflowId(), $intro->getJobId(), $intro->getJobStateId()), 1);

        $this->assertSame(JobState::STATUS_FAILURE, $this->stateRepository->getLastJobState($workflowState->getId(), 'intro')->getStatus());
        $state = $this->stateRepository->getWorkflowState($workflowState->getId());
        $this->assertSame(WorkflowState::STATUS_FAILURE, $state->getStatus());
        $this->assertNotNull($state->getEndedAt());
        $this->assertSame([WorkflowState::STATUS_FAILURE, 0], $this->updates[array_key_last($this->updates)]);

        $this->updates = [];
        $this->orchestrator->retryFailedJobs($workflowState->getId());

        $this->assertSame([[WorkflowState::STATUS_STARTED, 0]], $this->updates);
    }

    public function testRetryOfAnUnknownJobStateLeavesTheWorkflowAlone(): void
    {
        $workflowState = $this->orchestrator->startWorkflow('Echo something');
        $this->updates = [];

        ($this->handler)(new JobConsumer($workflowState->getId(), 'intro', 'unknown-job-state'), 1);

        $this->assertSame(WorkflowState::STATUS_STARTED, $this->stateRepository->getWorkflowState($workflowState->getId())->getStatus());
        $this->assertSame([], $this->updates);
    }

    public function testCancelledWorkflowIsNotified(): void
    {
        $workflowState = $this->orchestrator->startWorkflow('Echo something');
        $this->updates = [];

        $this->orchestrator->cancelWorkflow($workflowState->getId());

        $this->assertSame([[WorkflowState::STATUS_CANCELLED, 0]], $this->updates);
    }

    private function consumeJobs(): void
    {
        while (null !== $jobTrigger = $this->trigger->shift()) {
            ($this->handler)(new JobConsumer($jobTrigger->getWorkflowId(), $jobTrigger->getJobId(), $jobTrigger->getJobStateId()), 0);
        }
    }

    private function getTransactionDepth(): int
    {
        $depth = 0;
        foreach ($this->stateRepository->getLogs() as $log) {
            if ('beginTransaction' === $log[0]) {
                ++$depth;
            } elseif ('endTransaction' === $log[0]) {
                --$depth;
            }
        }

        return $depth;
    }
}
