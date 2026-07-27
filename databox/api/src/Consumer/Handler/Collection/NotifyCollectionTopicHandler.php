<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use App\Consumer\Handler\Asset\AbstractNotifyHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyCollectionTopicHandler extends AbstractNotifyHandler
{
    public function __invoke(NotifyCollectionTopic $message): void
    {
        $collectionId = $message->getCollectionId();
        $collection = $this->em->find(Collection::class, $collectionId);

        $event = $message->getEvent();
        $topic = match ($event) {
            Collection::EVENT_ASSET_ADD => 'collection_asset_add',
            Collection::EVENT_ASSET_REMOVE => 'collection_asset_remove',
            default => throw new \InvalidArgumentException(sprintf('Invalid collection event "%s"', $event)),
        };

        $asset = $message->getAssetId() ? $this->em->find(Asset::class, $message->getAssetId()) : null;

        $uri = '/collections/'.$collectionId;
        if (null !== $asset) {
            $uri .= '#asset-'.$asset->getId();
        }

        $assetName = null;
        if ($asset instanceof Asset) {
            $assetName = $this->assetNameResolver->resolveNameAsString($asset) ?? $message->getAssetName() ?? $asset->getId();
        }
        $assetName ??= 'Undefined';
        $authorId = $message->getAuthorId();

        $notificationParams = [
            'collectionName' => $collection?->getName() ?? $collection?->getId() ?? $message->getAssetName() ?? 'Undefined',
            'assetName' => $assetName,
            'url' => $uri,
            'author' => $this->getUsername($authorId),
            'authorId' => $authorId,
        ];

        $this->notifierManager->notify([
            new NotifySelectorDto(
                event: $event,
                objectType: $collection::OBJECT_TYPE,
                objectId: $collection->getId(),
                topic: new TopicDto(
                    $topic,
                    $notificationParams
                ),
            ),
            new NotifyOptions(
                excludeUserId: $message->getAuthorId()
            ),
        ]);
    }
}
