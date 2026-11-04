<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Workflow\WorkflowState;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Numbers the runs of a workflow on an asset (1 for its first ingest, 2 for
 * the next one...), so that they can be told apart without their UUID.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: WorkflowState::class)]
final class WorkflowStateListener
{
    public function prePersist(WorkflowState $workflowState, PrePersistEventArgs $args): void
    {
        $asset = $workflowState->getAsset();
        if (null !== $workflowState->getNumber() || null === $asset) {
            return;
        }

        $count = $args->getObjectManager()->createQueryBuilder()
            ->select('COUNT(w.id)')
            ->from(WorkflowState::class, 'w')
            ->andWhere('w.asset = :asset')
            ->andWhere('w.name = :name')
            ->setParameter('asset', $asset->getId())
            ->setParameter('name', $workflowState->getName())
            ->getQuery()
            ->getSingleScalarResult();

        $workflowState->setNumber((int) $count + 1);
    }
}
