<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Discussion;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Asset\ObjectNotifier;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Discussion\Message;
use App\Entity\ObjectTitleInterface;
use App\Repository\Discussion\MessageRepository;
use App\Service\DiscussionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class PostDiscussionMessageHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private NotifierInterface $notifier,
        private DiscussionManager $discussionManager,
        private ObjectNotifier $assetNotifier,
    ) {
    }

    public function __invoke(PostDiscussionMessage $message): void
    {
        /** @var Message $message */
        $message = $this->messageRepository->find($message->getId());
        if (!$message) {
            return;
        }

        $object = $this->discussionManager->getThreadObject($message->getThread());
        $authorId = $message->getAuthorId();

        $notificationId = 'databox-discussion-new-comment';
        $params = [
            'object' => $object instanceof ObjectTitleInterface ? $object->getObjectTitle() : 'Undefined Object',
        ];

        if ($object instanceof Asset) {
            $params['url'] = '/assets/'.$object->getId().'#discussion-'.$message->getId();

            $this->assetNotifier->notifyObject(
                $object,
                Asset::EVENT_NEW_COMMENT,
                $notificationId,
                $authorId,
                $params,
                subscribeAuthor: true,
            );

            foreach ($object->getCollections() as $assetCollection) {
                $collection = $assetCollection->getCollection();
                $params['collection'] = $collection->getAbsoluteTitle();

                $this->assetNotifier->notifyObject(
                    $collection,
                    Collection::EVENT_ASSET_NEW_COMMENT,
                    $notificationId,
                    $authorId,
                    $params
                );
            }
        } else {
            $topicKey = $message->getThread()->getNotificationKey();
            $params['author'] = $this->notifier->getUsername($authorId);
            $this->notifier->addTopicSubscribers($topicKey, [$authorId]);

            $this->notifier->notifyTopic($topicKey, $authorId, $notificationId, $params);
        }
    }
}
