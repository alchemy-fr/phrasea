<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Service\Asset\ObjectNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyCollectionTopicHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ObjectNotifier $objectNotifier,
    ) {
    }

    public function __invoke(NotifyCollectionTopic $message): void
    {
        if (!$this->objectNotifier->isEnabled()) {
            return;
        }

        $collectionId = $message->getCollectionId();
        $collection = $this->em->find(Collection::class, $collectionId);

        $notificationId = match ($message->getEvent()) {
            Collection::EVENT_ASSET_ADD => 'databox-collection-asset-add',
            Collection::EVENT_ASSET_REMOVE => 'databox-collection-asset-remove',
            default => throw new \InvalidArgumentException(sprintf('Invalid collection event "%s"', $message->getEvent())),
        };

        $asset = $message->getAssetId() ? $this->em->find(Asset::class, $message->getAssetId()) : null;

        $uri = '/collections/'.$collectionId;
        if (null !== $asset) {
            $uri .= '#asset-'.$asset->getId();
        }

        $notificationParams = [
            'collectionTitle' => $collection?->getTitle() ?? $collection?->getId() ?? $message->getAssetTitle() ?? 'Undefined',
            'assetTitle' => $asset?->getTitle() ?? $message->getAssetTitle() ?? $asset?->getId() ?? 'Undefined',
            'url' => $uri,
        ];

        $this->objectNotifier->notifyObject(
            $collection,
            $message->getEvent(),
            $notificationId,
            $message->getAuthorId(),
            $notificationParams,
        );
    }
}
