<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Webhook\WebhookTrigger;

final readonly class WebhookHandler
{
    public function __construct(private WebhookTrigger $webhookTrigger)
    {
    }

    public function __invoke(WebhookEvent $message): void
    {
        $this->webhookTrigger->triggerEvent($message->getEvent(), $message->getPayload());
    }
}
