<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyAssetTopicHandler extends AbstractNotifyHandler
{
    public function __invoke(NotifyAssetTopic $message): void
    {
        $assetId = $message->getAssetId();
        $asset = $this->em->find(Asset::class, $assetId);
        $event = $message->getEvent();

        $topic = match ($event) {
            Asset::EVENT_UPDATE => 'asset:update',
            Asset::EVENT_DELETE => 'asset:delete',
            Asset::EVENT_NEW_COMMENT => 'asset:new_comment',
            default => throw new \InvalidArgumentException(sprintf('Invalid asset event "%s"', $event)),
        };

        $authorId = $message->getAuthorId();
        $assetName = $asset ? $this->assetNameResolver->resolveNameAsString($asset) : null;

        $notificationParams = [
            'name' => $assetName ?? $message->getAssetName() ?? $asset?->getId() ?? 'Undefined',
            'assetId' => $assetId,
            'url' => '/assets/'.$assetId,
            'author' => $this->getUsername($authorId),
            'authorId' => $authorId,
        ];

        $assetTopic = new TopicDto($topic, $notificationParams);
        if (null === $asset) {
            // The asset is already gone (e.g. delete event): the selector only needs its id, which
            // comes from the message.
            $selectors = [new NotifySelectorDto($event, objectType: Asset::OBJECT_TYPE, objectId: $assetId, topic: $assetTopic)];
        } else {
            $readers = [];
            $selectors = [$this->createReadersSelector(
                new NotifySelectorDto($event, objectType: Asset::OBJECT_TYPE, objectId: $assetId),
                $asset,
                $assetTopic,
                $readers,
            )];

            if (Asset::EVENT_UPDATE === $event) {
                foreach ($asset->getCollections() as $assetCollection) {
                    $notificationParams['collection'] = $assetCollection->getCollection()->getAbsoluteName();

                    $selectors[] = $this->createReadersSelector(
                        new NotifySelectorDto($event, objectType: Collection::OBJECT_TYPE, objectId: $assetCollection->getCollection()->getId()),
                        $asset,
                        new TopicDto($topic, $notificationParams),
                        $readers,
                    );
                }
            }
            $selectors = array_values(array_filter($selectors));
        }

        $this->notifierManager->notify($selectors, new NotifyOptions(excludeUserId: $authorId));
    }
}
