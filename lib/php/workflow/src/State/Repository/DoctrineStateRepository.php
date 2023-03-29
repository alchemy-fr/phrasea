<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\Doctrine\Entity\JobState as JobStateEntity;
use Alchemy\Workflow\Doctrine\Entity\WorkflowState as WorkflowStateEntity;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

class DoctrineStateRepository implements LockAwareStateRepositoryInterface
{
    private EntityManagerInterface $em;

    private array $jobs = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getWorkflowState(string $id): WorkflowState
    {
        $entity = $this->em->getRepository(WorkflowStateEntity::class)->find($id);
        if (!$entity instanceof WorkflowStateEntity) {
            throw new InvalidArgumentException(sprintf('Workflow state "%s" does not exist', $id));
        }

        /** @var WorkflowState $state */
        $state = unserialize($entity->getState());
        $state->setStateRepository($this);

        return $state;
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $entity = $this->em->getRepository(WorkflowStateEntity::class)->find($state->getId());
        if (!$entity instanceof WorkflowStateEntity) {
            $entity = new WorkflowStateEntity($state->getId());
        }

        $entity->setState(serialize($state));

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $entity = $this->fetchJobEntity($workflowId, $jobId);
        if (!$entity instanceof JobStateEntity) {
            return null;
        }

        /** @var JobState $state */
        $state = unserialize($entity->getState());

        return $state;
    }

    public function acquireJobLock(string $workflowId, string $jobId): void
    {
        $this->em->beginTransaction();
        try {
            $entity = $this->em->getRepository(JobStateEntity::class)
                ->createQueryBuilder('t')
                ->select('t')
                ->andWhere('t.workflow = :w')
                ->andWhere('t.jobId = :j')
                ->setParameters([
                    'w' => $workflowId,
                    'j' => $jobId,
                ])
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            if ($entity instanceof JobStateEntity) {
                $this->jobs[$workflowId][$jobId] = $entity;
            }
        } catch (Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function releaseJobLock(string $workflowId, string $jobId): void
    {
        $this->em->commit();
    }

    public function persistJobState(JobState $state): void
    {
        $entity = $this->fetchJobEntity($state->getWorkflowId(), $state->getJobId());
        if (!$entity instanceof JobStateEntity) {
            $entity = new JobStateEntity(
                $this->em->getReference(WorkflowStateEntity::class, $state->getWorkflowId()),
                $state->getJobId()
            );
        }

        $entity->setState(serialize($state));

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    private function fetchJobEntity(string $workflowId, string $jobId): ?JobStateEntity
    {
        if (isset($this->jobs[$workflowId][$jobId])) {
            return $this->jobs[$workflowId][$jobId];
        }

        $entity = $this->em->getRepository(JobStateEntity::class)->findOneBy([
            'workflow' => $workflowId,
            'jobId' => $jobId,
        ]);

        if ($entity) {
            $this->jobs[$workflowId][$jobId] = $entity;
        }

        return $entity;
    }
}
