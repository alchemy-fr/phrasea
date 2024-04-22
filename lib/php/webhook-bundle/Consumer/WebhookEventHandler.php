<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WebhookEventHandler
{
    public function __construct(private WebhookTrigger $webhookTrigger)
    {
    }

    public function __invoke(WebhookEvent $message): void
    {
        $this->webhookTrigger->triggerEvent($message->getEvent(), $message->getPayload());
    }
}
