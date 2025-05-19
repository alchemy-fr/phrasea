<?php

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Listener\JobUpdateEvent;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\Repository\TransactionalStateRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

final class JobStateManager
{
    /**
     * @var JobUpdateEvent[]
     */
    private array $eventsToDispatch = [];

    private readonly EventDispatcherInterface $eventDispatcher;

    public function __construct(
        private readonly StateRepositoryInterface $stateRepository,
        ?EventDispatcherInterface $eventDispatcher = null,
    )
    {
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
    }

    public function acquireJobLock(string $workflowId, string $jobStateId): void
    {
        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->acquireJobLock($workflowId, $jobStateId);
        }
    }

    public function getJobState(string $workflowId, string $jobStateId): ?JobState
    {
        return $this->stateRepository->getJobState($workflowId, $jobStateId);
    }

    public function releaseJobLock(string $workflowId, string $jobStateId): void
    {
        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->releaseJobLock($workflowId, $jobStateId);
        }
    }

    public function persistJobState(JobState $jobState, bool $releaseLock = true): void
    {
        $this->stateRepository->persistJobState($jobState);

        if ($releaseLock) {
            $this->releaseJobLock($jobState->getWorkflowId(), $jobState->getId());
        }

        $this->dispatchEvent(new JobUpdateEvent($jobState->getWorkflowId(), $jobState->getJobId(), $jobState->getId(), $jobState->getStatus()));
    }

    private function dispatchEvent(JobUpdateEvent $event): void
    {
        if ($this->stateRepository instanceof TransactionalStateRepositoryInterface) {
            $this->eventsToDispatch[] = $event;
        } else {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function wrapInTransaction(callable $callback): mixed
    {
        if ($this->stateRepository instanceof TransactionalStateRepositoryInterface) {
            return $this->stateRepository->transactional($callback);
        }

        return $callback();
    }

    public function flushEvents(): void
    {
        $events = $this->eventsToDispatch;
        $this->eventsToDispatch = [];
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
