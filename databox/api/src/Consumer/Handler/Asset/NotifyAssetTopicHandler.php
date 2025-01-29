<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use App\Asset\ObjectNotifier;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyAssetTopicHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ObjectNotifier $objectNotifier,
    ) {
    }

    public function __invoke(NotifyAssetTopic $message): void
    {
        $assetId = $message->getAssetId();
        $asset = $this->em->find(Asset::class, $assetId);

        $notificationId = match ($message->getEvent()) {
            Asset::EVENT_UPDATE => 'databox-asset-update',
            Asset::EVENT_DELETE => 'databox-asset-delete',
            Asset::EVENT_NEW_COMMENT => 'databox-asset-new-comment',
            default => throw new \InvalidArgumentException(sprintf('Invalid asset event "%s"', $message->getEvent())),
        };

        $notificationParams = [
            'title' => $asset?->getTitle() ?? $asset?->getId() ?? $message->getAssetTitle() ?? 'Undefined',
            'url' => '/assets/'.$assetId,
        ];

        $this->objectNotifier->notifyObject(
            $asset,
            $message->getEvent(),
            $notificationId,
            $message->getAuthorId(),
            $notificationParams,
        );

        if (Asset::EVENT_UPDATE === $message->getEvent()) {
            foreach ($asset->getCollections() as $assetCollection) {
                $notificationParams['collection'] = $assetCollection->getCollection()->getAbsoluteTitle();

                $this->objectNotifier->notifyObject(
                    $assetCollection->getCollection(),
                    Collection::EVENT_ASSET_UPDATE,
                    $notificationId,
                    $message->getAuthorId(),
                    $notificationParams
                );
            }
        }
    }
}
