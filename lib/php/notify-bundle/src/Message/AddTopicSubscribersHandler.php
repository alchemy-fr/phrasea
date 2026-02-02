<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\NotifyBundle\Service\NovuManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AddTopicSubscribersHandler
{
    public function __construct(
        private NovuManager $novuManager,
    ) {
    }

    public function __invoke(AddTopicSubscribers $message): void
    {
        $this->novuManager->addTopicSubscribers($message->getTopicKey(), $message->getSubscribers());
    }
}
