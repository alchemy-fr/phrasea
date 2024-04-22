<?php

namespace Alchemy\WebhookBundle\Consumer;

final readonly class WebhookTriggerMessage
{
    public function __construct(
        private string $webhookId,
        private string $event,
        private array $payload
    ) {
    }

    public function getWebhookId(): string
    {
        return $this->webhookId;
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
