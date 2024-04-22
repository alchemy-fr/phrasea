<?php

namespace Alchemy\MessengerBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class MessengerMessage
{
    final public const TAG = 'messenger.message';

    public function __construct(
        private string $queue
    ) {
    }

    public function getQueue(): string
    {
        return $this->queue;
    }
}
