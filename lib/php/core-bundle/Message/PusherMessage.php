<?php

namespace Alchemy\CoreBundle\Message;

final readonly class PusherMessage
{
    public function __construct(
        private string $channel,
        private string $event,
        private array $payload = []
    ) {
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
