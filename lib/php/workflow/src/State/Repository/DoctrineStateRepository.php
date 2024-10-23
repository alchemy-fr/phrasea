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

#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'clear')]
#[AsEventListener(event: WorkerMessageFailedEvent::class, method: 'clear')]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'clear')]
class DoctrineStateRepository implements LockAwareStateRepositoryInterface
{
    private array $jobs = [];
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

    public function clear(): void
    {
        $this->jobs = [];
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

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $entity = $this->fetchJobEntity($workflowId, $jobId);
        if (!$entity instanceof JobStateEntity) {
            return null;
        }

        return $entity->getJobState();
    }

    public function removeJobState(string $workflowId, string $jobId): void
    {
        $entity = $this->fetchJobEntity($workflowId, $jobId);
        if ($entity instanceof JobStateEntity) {
            $this->em->remove($entity);
            $this->em->flush();

            unset($this->jobs[$workflowId][$jobId]);
        }
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
    }

    public function acquireJobLock(string $workflowId, string $jobId): void
    {
        $this->em->beginTransaction();
        try {
            $entity = $this->createQueryBuilder($workflowId, $jobId)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            if ($entity instanceof JobStateEntity) {
                $this->jobs[$workflowId][$jobId] = $entity;
            } else {
                unset($this->jobs[$workflowId][$jobId]);
            }
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function createQueryBuilder(string $workflowId, string $jobId): QueryBuilder
    {
        return $this->em->getRepository($this->jobStateEntity)
            ->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.workflow = :w')
            ->andWhere('t.jobId = :j')
            ->setParameters([
                'w' => $workflowId,
                'j' => $jobId,
            ])
            ->addOrderBy('t.triggeredAt', 'DESC')
            ->setMaxResults(1);
    }

    public function releaseJobLock(string $workflowId, string $jobId): void
    {
        $this->em->commit();
    }

    public function persistJobState(JobState $state): void
    {
        $entity = null;
        if (JobState::STATUS_TRIGGERED !== $state->getStatus()) {
            $entity = $this->fetchJobEntity($state->getWorkflowId(), $state->getJobId());
        }
        if (!$entity instanceof JobStateEntity) {
            $jobStateEntity = $this->jobStateEntity;
            $entity = new $jobStateEntity(
                $this->em->getReference($this->workflowStateEntity, $state->getWorkflowId()),
                $state->getJobId()
            );

            $this->jobs[$entity->getWorkflow()->getId()][$entity->getJobId()] = $entity;
        }

        $entity->setState($state, $this->em);

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    private function fetchJobEntity(string $workflowId, string $jobId): ?JobStateEntity
    {
        if (isset($this->jobs[$workflowId][$jobId])) {
            return $this->jobs[$workflowId][$jobId];
        }

        $entity = $this->createQueryBuilder($workflowId, $jobId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($entity) {
            $this->jobs[$workflowId][$jobId] = $entity;
        } else {
            unset($this->jobs[$workflowId][$jobId]);
        }

        return $entity;
    }
}
