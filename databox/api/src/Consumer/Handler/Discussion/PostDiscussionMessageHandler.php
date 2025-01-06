<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Discussion;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\Discussion\Message;
use App\Repository\Discussion\MessageRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class PostDiscussionMessageHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private NotifierInterface $notifier,
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

        $this->notifier->addTopicSubscribers($topicKey, [$message->getAuthorId()]);
        $this->notifier->notifyTopic($topicKey, $message->getAuthorId(), 'databox-discussion-new-comment', [
            'author' => $message->getAuthorId(),
            'object' => 'ObjectSample',
        ]);
    }
}
