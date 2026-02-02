<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\NotifyBundle\Service\NovuManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RemoveTopicSubscribersHandler
{
    public function __construct(
        private NovuManager $novuManager,
    ) {
    }

    public function __invoke(RemoveTopicSubscribers $message): void
    {
        $this->novuManager->removeTopicSubscribers($message->getTopicKey(), $message->getSubscribers());
    }
}
