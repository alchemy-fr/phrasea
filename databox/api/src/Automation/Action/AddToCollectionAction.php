<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Automation\AutomationBatch;
use App\Entity\Core\Collection;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use App\Service\Collection\AssetCollectionAdder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds the assets to a collection, keeping them where they are (same checks as AddAssetsToCollectionProcessor).
 */
#[AsTaggedItem(index: self::TYPE)]
final class AddToCollectionAction extends AbstractAutomationAction
{
    public const string TYPE = 'add_to_collection';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetCollectionAdder $assetCollectionAdder,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $collectionId = self::normalizeId(self::getRequiredString($config, 'collectionId'));
        $collection = Uuid::isValid($collectionId) ? $this->em->find(Collection::class, $collectionId) : null;
        if (!$collection instanceof Collection) {
            self::throwViolations(['collectionId' => 'Collection not found.']);
        }
        if (!$this->isGranted(CollectionVoter::ASSET_CREATE, $collection)) {
            self::throwViolations(['collectionId' => 'You are not allowed to add assets to this collection.']);
        }

        return ['collectionId' => $collectionId];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $collection = $this->em->find(Collection::class, $batch->config['collectionId']);
        if (!$collection instanceof Collection) {
            foreach ($batch->assets as $asset) {
                $batch->state->markFailed($asset, 'Destination collection not found');
            }

            return;
        }

        if (!$this->isGranted(CollectionVoter::ASSET_CREATE, $collection)) {
            foreach ($batch->assets as $asset) {
                $batch->state->markSkipped($asset, 'Not allowed to add assets to the destination collection');
            }

            return;
        }

        $toAdd = [];
        foreach ($batch->assets as $asset) {
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                $batch->state->markSkipped($asset, 'Not allowed to read the asset');
                continue;
            }
            if (null !== $reason = $this->assetCollectionAdder->getRefusalReason($collection, $asset)) {
                $batch->state->markSkipped($asset, $reason);
                continue;
            }

            $toAdd[] = $asset;
        }

        if (empty($toAdd)) {
            return;
        }

        $this->assetCollectionAdder->add($collection, $toAdd);
        foreach ($toAdd as $asset) {
            $batch->state->markProcessed($asset);
        }
    }
}
