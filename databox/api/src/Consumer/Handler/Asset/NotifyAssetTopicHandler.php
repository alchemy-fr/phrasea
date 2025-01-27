<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyAssetTopicHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotifierInterface $notifier,
    ) {
    }

    public function __invoke(NotifyAssetTopic $message): void
    {
        $assetId = $message->getAssetId();
        $asset = $this->em->find(Asset::class, $assetId);

        $notificationId = match ($message->getEvent()) {
            Asset::EVENT_UPDATE => 'databox-asset-update',
            Asset::EVENT_DELETE => 'databox-asset-delete',
            default => throw new InvalidArgumentException(sprintf('Invalid asset event "%s"', $message->getEvent())),
        };

        $authorId = $message->getAuthorId();

        $notificationParams = [
            'author' => $this->notifier->getUsername($authorId),
            'title' => $asset?->getTitle() ?? $asset?->getId() ?? $message->getAssetTitle() ?? 'Undefined',
            'url' => '/assets/'.$assetId,
        ];

        $this->notifier->notifyTopic(Asset::getTopicKey($message->getEvent(), $assetId), $authorId, $notificationId, $notificationParams);

        if (Asset::EVENT_UPDATE === $message->getEvent()) {
            foreach ($asset->getCollections() as $assetCollection) {
                $this->notifier->notifyTopic(
                    Collection::getTopicKey(Collection::EVENT_ASSET_UPDATE, $assetCollection->getCollection()->getId()),
                    $authorId,
                    $notificationId,
                    $notificationParams
                );
            }
        }
    }
}
