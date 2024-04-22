<?php

namespace Alchemy\WebhookBundle\Consumer;

final readonly class WebhookEvent
{
    public function __construct(private string $event, private array $payload)
    {
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
