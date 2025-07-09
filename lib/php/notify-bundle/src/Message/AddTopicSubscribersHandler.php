<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\NotifyBundle\Service\NovuClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AddTopicSubscribersHandler
{
    public function __construct(
        private NovuClient $novuClient,
    ) {
    }

    public function __invoke(AddTopicSubscribers $message): void
    {
        $this->novuClient->addTopicSubscribers($message->getTopicKey(), $message->getSubscribers());
    }
}
