<?php

declare(strict_types=1);

namespace App\Service\Workflow;

use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Runs the ingest workflow of an asset again.
 */
final readonly class AssetWorkflowTrigger
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private EntityManagerInterface $em,
    ) {
    }

    public function trigger(Asset $asset, string $initiatorId): void
    {
        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $initiatorId,
            ]
        );
    }

    public function isIngestRunning(Asset $asset): bool
    {
        $running = $this->em->createQueryBuilder()
            ->select('w.id')
            ->from(WorkflowState::class, 'w')
            ->andWhere('w.asset = :asset')
            ->andWhere('w.name LIKE :name')
            ->andWhere('w.status = :status')
            ->setParameter('asset', $asset->getId())
            ->setParameter('name', AssetIngestWorkflowEvent::WORKFLOW_NAME.':%')
            ->setParameter('status', ModelWorkflowState::STATUS_STARTED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $running;
    }
}
