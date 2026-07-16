<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\Asset;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Notifies the followers of a collection when an asset is added to it.
 *
 * Users subscribe to a collection with:
 *   $subscriptionManager->subscribe($userId, 'collection', $collectionId);
 */
#[AsDoctrineListener(Events::postPersist)]
final readonly class AssetNotificationListener
{
    public function __construct(
        private NotifierManager $notifier,
        private PostFlushStack $postFlushStack,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $asset = $args->getObject();

        if (!$asset instanceof Asset || !$this->notifier->isEnabled()) {
            return;
        }

        $collectionId = $asset->getReferenceCollectionId();
        if (null === $collectionId) {
            return;
        }

        $params = [
            'assetId' => $asset->getId(),
            'collectionId' => $collectionId,
            'collectionName' => $asset->getReferenceCollection()?->getName(),
            'workspaceName' => $asset->getWorkspace()?->getName(),
        ];
        $ownerId = $asset->getOwnerId();

        $this->postFlushStack->addCallback(function () use ($collectionId, $params, $ownerId): void {
            $this->notifier->notifyObject('collection', $collectionId, 'asset_added', $params, [
                'exclude_user_id' => $ownerId,
            ]);
        });
    }
}
