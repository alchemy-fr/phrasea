<?php

namespace Alchemy\NotifyBundle\Message;

final readonly class UpdateSubscribers
{
    public function __construct(
        private array $subscribers,
    ) {
    }

    public function getSubscribers(): array
    {
        return $this->subscribers;
    }
}
