<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\NotifyBundle\Service\NovuClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RemoveTopicSubscribersHandler
{
    public function __construct(
        private NovuClient $novuClient,
    ) {
    }

    public function __invoke(RemoveTopicSubscribers $message): void
    {
        $this->novuClient->removeTopicSubscribers($message->getTopicKey(), $message->getSubscribers());
    }
}
