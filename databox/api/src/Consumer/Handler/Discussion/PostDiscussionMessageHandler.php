<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Discussion;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Discussion\Message;
use App\Entity\FollowableInterface;
use App\Entity\ObjectDisplayableNameInterface;
use App\Repository\Discussion\MessageRepository;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Discussion\DiscussionManager;
use App\Service\Discussion\MentionExtractor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class PostDiscussionMessageHandler
{
    private const string TOPIC = 'discussion_new_comment';
    private const string THREAD_OBJECT_TYPE = 'thread';
    private const string THREAD_EVENT = 'discussion:new-comment';

    public function __construct(
        private MessageRepository $messageRepository,
        private NotifierManager $notifierManager,
        private SubscriptionManager $subscriptionManager,
        private DiscussionManager $discussionManager,
        private MentionExtractor $mentionExtractor,
        private AssetNameResolver $assetNameResolver,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(PostDiscussionMessage $message): void
    {
        $entity = $this->messageRepository->find($message->getId());
        if (!$entity instanceof Message) {
            return;
        }
        $message = $entity;

        $thread = $message->getThread();
        $authorId = $message->getAuthorId();
        if (null === $thread || null === $authorId) {
            return;
        }

        $object = $this->discussionManager->getThreadObject($thread);

        if ($object instanceof Asset) {
            $objectName = $this->assetNameResolver->resolveNameAsString($object) ?? $object->getId();
        } elseif ($object instanceof ObjectDisplayableNameInterface) {
            $objectName = $object->getObjectDisplayName();
        } else {
            $objectName = 'Undefined Object';
        }

        $params = [
            'object' => $objectName,
            'objectId' => $object->getId(),
            'authorId' => $authorId,
            'author' => $this->getUsername($authorId),
        ];
        if ($object instanceof Asset) {
            $params['url'] = '/assets/'.$object->getId().'#discussion-'.$message->getId();
        }

        // Thread participants (comment author + mentioned users) subscribe to the
        // thread so they get notified of the following comments.
        $participants = [$authorId];
        foreach ($this->mentionExtractor->extractMentions($message->getContent() ?? '') as $userId => $username) {
            $participants[] = (string) $userId;
        }
        foreach (array_unique($participants) as $userId) {
            $this->subscriptionManager->subscribe($userId, new NotifySelectorDto(
                event: self::THREAD_EVENT,
                objectType: self::THREAD_OBJECT_TYPE,
                objectId: $thread->getId(),
            ));
        }

        $selectors = [
            new NotifySelectorDto(
                event: self::THREAD_EVENT,
                objectType: self::THREAD_OBJECT_TYPE,
                objectId: $thread->getId(),
                topic: new TopicDto(self::TOPIC, $params),
            ),
        ];

        // Followers of the asset and of its collections.
        if ($object instanceof Asset) {
            $this->autoSubscribeOwner($object, Asset::EVENT_NEW_COMMENT);
            $selectors[] = new NotifySelectorDto(
                event: Asset::EVENT_NEW_COMMENT,
                objectType: Asset::OBJECT_TYPE,
                objectId: $object->getId(),
                topic: new TopicDto(self::TOPIC, $params),
            );

            foreach ($object->getCollections() as $assetCollection) {
                $collection = $assetCollection->getCollection();
                $this->autoSubscribeOwner($collection, Collection::EVENT_ASSET_NEW_COMMENT);
                $selectors[] = new NotifySelectorDto(
                    event: Collection::EVENT_ASSET_NEW_COMMENT,
                    objectType: Collection::OBJECT_TYPE,
                    objectId: $collection->getId(),
                    topic: new TopicDto(self::TOPIC, $params + ['collection' => $collection->getAbsoluteName()]),
                );
            }
        }

        // A single notify() call deduplicates recipients matched by several
        // selectors, and excludes the comment author.
        $this->notifierManager->notify($selectors, new NotifyOptions(excludeUserId: $authorId));
    }

    private function autoSubscribeOwner(FollowableInterface $object, string $event): void
    {
        if ($object->isAutoSubscribeOwner() && null !== $object->getOwnerId()) {
            $this->subscriptionManager->subscribe($object->getOwnerId(), new NotifySelectorDto(
                event: $event,
                objectType: $object->getObjectType(),
                objectId: $object->getId(),
            ));
        }
    }

    private function getUsername(string $userId): string
    {
        $user = $this->userRepository->getUser($userId);

        return $user['username'] ?? 'Deleted User';
    }
}
