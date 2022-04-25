<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class WebhookHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'webhook';

    private WebhookTrigger $webhookTrigger;

    public function __construct(WebhookTrigger $webhookTrigger)
    {
        $this->webhookTrigger = $webhookTrigger;
    }

    public function handle(EventMessage $message): void
    {
        $p = $message->getPayload();
        $this->webhookTrigger->triggerEvent($p['event'], $p['payload']);
    }

    public static function createEvent(string $event, array $payload): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'event'=> $event,
            'payload' => $payload,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
