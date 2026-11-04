<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Automation\AutomationBatch;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Security\Voter\AssetVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Moves the assets to the trash (soft delete, same check as DELETE /assets).
 */
#[AsTaggedItem(index: self::TYPE)]
final class TrashAction extends AbstractAutomationAction
{
    public const string TYPE = 'trash';

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $ids = [];
        foreach ($batch->assets as $asset) {
            if (!$this->isGranted(AssetVoter::DELETE, $asset)) {
                $batch->state->markSkipped($asset, 'Not allowed to delete the asset');
                continue;
            }

            if ($asset->isAssetDeleted()) {
                $batch->state->markProcessed($asset);
                continue;
            }
            $ids[] = $asset->getId();
        }

        if (empty($ids)) {
            return;
        }

        $this->bus->dispatch(new AssetsDelete($ids));
        foreach ($ids as $id) {
            $batch->state->markProcessed($id);
        }
    }
}
