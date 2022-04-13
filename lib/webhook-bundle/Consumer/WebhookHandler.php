<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Entity\Webhook;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class WebhookHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'webhook';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $p = $message->getPayload();
        $event = $p['event'];
        $payload = $p['payload'];

        /** @var Webhook[] $webhooks */
        $webhooks = $this->getEntityManager()->getRepository(Webhook::class)->findBy([
            'active' => true,
        ]);

        foreach ($webhooks as $webhook) {
            if ($webhook->hasEvent($event)) {
                $this->eventProducer->publish(WebhookTriggerHandler::createEvent($webhook->getId(), $event, $payload));
            }
        }
    }

    public static function createEvent(string $event, array $payload): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'event '=> $event,
            'payload' => $payload,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
