<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Discussion;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
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
        private UserRepository $userRepository,
        private DiscussionManager $discussionManager,
    ) {
    }

    public function __invoke(PostDiscussionMessage $message): void
    {
        /** @var Message $message */
        $message = $this->messageRepository->find($message->getId());
        if (!$message) {
            return;
        }

        $topicKey = $message->getThread()->getKey();

        $object = $this->discussionManager->getThreadObject($message->getThread());
        $authorId = $message->getAuthorId();
        $author = $this->userRepository->getUser($authorId);

        $this->notifier->addTopicSubscribers($topicKey, [$authorId]);
        $this->notifier->notifyTopic($topicKey, $authorId, 'databox-discussion-new-comment', [
            'author' => $author ? $author['username'] : 'Deleted user',
            'object' => $object instanceof ObjectTitleInterface ? $object->getObjectTitle() : 'Undefined object',
        ]);
    }
}
