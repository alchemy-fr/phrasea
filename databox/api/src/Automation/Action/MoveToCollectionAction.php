<?php

declare(strict_types=1);

namespace App\Automation\Action;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Automation\AutomationBatch;
use App\Consumer\Handler\Asset\AssetMove;
use App\Entity\Core\Collection;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Moves the assets to a collection of their workspace (same checks as MoveAssetProcessor).
 */
#[AsTaggedItem(index: self::TYPE)]
final class MoveToCollectionAction extends AbstractAutomationAction
{
    public const string TYPE = 'move_to_collection';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly IriConverterInterface $iriConverter,
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

        $destination = (string) $this->iriConverter->getIriFromResource($collection);
        foreach ($batch->assets as $asset) {
            if ($asset->getWorkspaceId() !== $collection->getWorkspaceId()) {
                $batch->state->markSkipped($asset, 'The asset cannot be moved to another workspace');
                continue;
            }
            if (!$this->isGranted(AbstractVoter::EDIT, $asset)) {
                $batch->state->markSkipped($asset, 'Not allowed to edit the asset');
                continue;
            }

            if ($asset->getReferenceCollection()?->getId() !== $collection->getId()) {
                $this->bus->dispatch(new AssetMove($asset->getId(), $destination));
            }
            $batch->state->markProcessed($asset);
        }
    }
}
