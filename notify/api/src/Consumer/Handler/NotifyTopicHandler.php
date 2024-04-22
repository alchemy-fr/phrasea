<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Topic\TopicManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class NotifyTopicHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private TopicManager $topicManager
    ) {
    }

    public function __invoke(NotifyTopic $message): void
    {
        $contacts = $this->topicManager->getSubscriptions($message->getTopic());
        foreach ($contacts as $contact) {
            $this->bus->dispatch(new NotifyUser(
                $contact->getContact()->getUserId(),
                $message->getTemplate(),
                $message->getParameters(),
            ));
        }
    }
}
