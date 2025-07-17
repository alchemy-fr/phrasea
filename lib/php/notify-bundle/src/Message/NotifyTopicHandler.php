<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\NotifyBundle\Service\NovuClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotifyTopicHandler
{
    public function __construct(
        private NovuClient $novuClient,
    ) {
    }

    public function __invoke(NotifyTopic $message): void
    {
        $this->novuClient->notifyTopic(
            $message->topicKey,
            $message->authorId,
            $message->notificationId,
            $message->parameters,
            $message->options
        );
    }
}
