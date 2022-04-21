<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Consumer\WebhookTriggerHandler;
use Alchemy\WebhookBundle\Entity\Webhook;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;

class WebhookTrigger
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;

    public function __construct(EventProducer $eventProducer, EntityManagerInterface $em)
    {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
    }

    public function triggerEvent(string $event, array $payload): void
    {
        $webhooks = $this->getWebhooksForEvent($event);

        foreach ($webhooks as $webhook) {
            if ($webhook->hasEvent($event)) {
                $this->eventProducer->publish(WebhookTriggerHandler::createEvent($webhook->getId(), $event, $payload));
            }
        }
    }

    public function getWebhooksForEvent(string $event): array
    {
        /** @var Webhook[] $webhooks */
        $webhooks = $this->em->getRepository(Webhook::class)->findBy([
            'active' => true,
        ]);

        return array_filter($webhooks, function (Webhook $webhook) use ($event): bool {
            return $webhook->hasEvent($event);
        });
    }
}
