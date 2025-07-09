<?php

namespace Alchemy\NotifyBundle\Message;

final readonly class RemoveTopicSubscribers
{
    public function __construct(
        private string $topicKey,
        private array $subscribers,
    ) {
    }

    public function getTopicKey(): string
    {
        return $this->topicKey;
    }

    public function getSubscribers(): array
    {
        return $this->subscribers;
    }
}
