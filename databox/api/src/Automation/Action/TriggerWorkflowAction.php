<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Automation\AutomationBatch;
use App\Security\Voter\AbstractVoter;
use App\Service\Workflow\AssetWorkflowTrigger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Runs the ingest workflow of each asset again, on behalf of the owner of the saved search
 * (same check as PUT /assets/{id}/trigger-workflow).
 */
#[AsTaggedItem(index: self::TYPE)]
final class TriggerWorkflowAction extends AbstractAutomationAction
{
    public const string TYPE = 'trigger_workflow';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetWorkflowTrigger $assetWorkflowTrigger,
    ) {
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        foreach ($batch->assets as $asset) {
            if (!$this->isGranted(AbstractVoter::EDIT, $asset)) {
                $batch->state->markSkipped($asset, 'Not allowed to edit the asset');
                continue;
            }

            try {
                $this->assetWorkflowTrigger->trigger($asset, $batch->context->ownerId);
            } catch (\Throwable $e) {
                if (!$this->em->isOpen()) {
                    throw $e;
                }
                $batch->state->markFailed($asset, $e->getMessage());
                continue;
            }

            $batch->state->markProcessed($asset);
        }
    }
}
