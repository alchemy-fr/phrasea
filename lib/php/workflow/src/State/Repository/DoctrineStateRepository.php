<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\Doctrine\Entity\JobState as JobStateEntity;
use Alchemy\Workflow\Doctrine\Entity\WorkflowState as WorkflowStateEntity;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'flush')]
#[AsEventListener(event: WorkerMessageFailedEvent::class, method: 'flush')]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'flush')]
class DoctrineStateRepository implements LockAwareStateRepositoryInterface, TransactionalStateRepositoryInterface
{
    use JobStatusCacheTrait;

    private readonly string $workflowStateEntity;
    private readonly string $jobStateEntity;

    public function __construct(
        private readonly EntityManagerInterface $em,
        ?string $workflowStateEntity = null,
        ?string $jobStateEntity = null,
    ) {
        $this->workflowStateEntity = $workflowStateEntity ?? WorkflowStateEntity::class;
        $this->jobStateEntity = $jobStateEntity ?? JobStateEntity::class;
    }

    public function getWorkflowState(string $id): WorkflowState
    {
        $entity = $this->em->getRepository($this->workflowStateEntity)->find($id);
        if (!$entity instanceof WorkflowStateEntity) {
            throw new \InvalidArgumentException(sprintf('Workflow state "%s" does not exist', $id));
        }

        $state = $entity->getWorkflowState();
        $state->setStateRepository($this);

        return $state;
    }

    public function flush(): void
    {
        $this->clearCache();
    }

    public function createJobState(string $workflowId, string $jobId): JobState
    {
        $number = $this->createListQueryBuilder($workflowId, $jobId)
            ->select('MAX(t.number) + 1')
            ->getQuery()
            ->getSingleScalarResult();

        return new JobState(
            $workflowId,
            $jobId,
            JobState::STATUS_TRIGGERED,
            null,
            $number ?? 0,
        );
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $workflowStateEntity = $this->workflowStateEntity;
        $entity = $this->em->getRepository($workflowStateEntity)->find($state->getId());
        if (!$entity instanceof WorkflowStateEntity) {
            $entity = new $workflowStateEntity($state->getId());
        }

        $entity->setState($state, $this->em);

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    public function getJobState(string $workflowId, string $jobStateId): ?JobState
    {
        return $this->fetchJobState($workflowId, $jobStateId)?->getJobState();
    }

    public function getLastJobState(string $workflowId, string $jobId): ?JobState
    {
        return $this->fetchLastJobState($workflowId, $jobId)?->getJobState();
    }

    public function getJobStates(string $workflowId, string $jobId): array
    {
        $entities = $this->fetchJobStates($workflowId, $jobId);
        if (empty($entities)) {
            return [];
        }

        return array_map(fn (JobStateEntity $state) => $state->getJobState(), $entities);
    }

    public function removeJobState(string $workflowId, string $jobStateId): void
    {
        $entity = $this->fetchJobState($workflowId, $jobStateId);
        if ($entity instanceof JobStateEntity) {
            $this->em->remove($entity);
            $this->em->flush();

            $this->removeJobStateFromCache($workflowId, $jobStateId);
        }
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
    }

    public function acquireJobLock(string $workflowId, string $jobStateId): void
    {
        $entity = $this->em
            ->getRepository($this->jobStateEntity)
            ->find($jobStateId, LockMode::PESSIMISTIC_WRITE);

        $this->cacheJobState($workflowId, $jobStateId, $entity);
    }

    private function createListQueryBuilder(string $workflowId, string $jobId): QueryBuilder
    {
        return $this->em->getRepository($this->jobStateEntity)
            ->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.workflow = :w')
            ->andWhere('t.jobId = :j')
            ->setParameters([
                'w' => $workflowId,
                'j' => $jobId,
            ]);
    }

    public function releaseJobLock(string $workflowId, string $jobStateId): void
    {
    }

    public function persistJobState(JobState $state): void
    {
        $entity = null;
        if (JobState::STATUS_TRIGGERED !== $state->getStatus()) {
            $entity = $this->fetchJobState($state->getWorkflowId(), $state->getId());
        }
        if (!$entity instanceof JobStateEntity) {
            $jobStateEntity = $this->jobStateEntity;
            $entity = new $jobStateEntity(
                $state->getId(),
                $this->em->getReference($this->workflowStateEntity, $state->getWorkflowId()),
                $state->getJobId()
            );

            $this->statuses[$state->getId()] = $state;
            $this->statuses[$entity->getWorkflow()->getId()][$entity->getJobId()][] = $entity;
        }

        $entity->setState($state, $this->em);

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    private function fetchJobState(string $workflowId, string $jobStateId): ?JobStateEntity
    {
        $state = $this->statuses[$jobStateId] ?? null;
        if ($state instanceof JobStateEntity) {
            return $state;
        }

        $state = $this->em->getRepository($this->jobStateEntity)
            ->findOneBy(['id' => $jobStateId]);

        $this->cacheJobState($workflowId, $jobStateId, $state);

        return $state;
    }

    private function fetchLastJobState(string $workflowId, string $jobId): ?JobStateEntity
    {
        $state = $this->lastByJobId[$workflowId][$jobId] ?? null;
        if ($state instanceof JobStateEntity) {
            return $state;
        }

        $state = $this->createListQueryBuilder($workflowId, $jobId)
            ->addOrderBy('t.triggeredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($state) {
            $this->cacheLastJobState($workflowId, $jobId, $state);
        }

        return $state;
    }

    /**
     * @return JobStateEntity[]
     */
    private function fetchJobStates(string $workflowId, string $jobId): array
    {
        $statuses = $this->statusesByJobId[$workflowId][$jobId] ?? null;
        if (null !== $statuses) {
            return $statuses;
        }

        $states = $this->createListQueryBuilder($workflowId, $jobId)
            ->addOrderBy('t.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult();

        $this->cacheJobStates($workflowId, $jobId, $states);

        return $states;
    }

    public function acquireWorkflowLock(string $workflowId): void
    {
        $entity = $this->em
            ->getRepository($this->workflowStateEntity)
            ->find($workflowId, LockMode::PESSIMISTIC_WRITE);

        $this->cacheWorkflowState($workflowId, $entity);
    }

    public function releaseWorkflowLock(string $workflowId): void
    {
    }

    public function transactional(callable $callback)
    {
        $this->em->beginTransaction();
        try {
            $response = $callback();
            $this->em->commit();

            return $response;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
