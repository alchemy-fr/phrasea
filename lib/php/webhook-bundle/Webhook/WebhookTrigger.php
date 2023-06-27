<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Consumer\WebhookTriggerHandler;
use Alchemy\WebhookBundle\Entity\Webhook;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;

class WebhookTrigger
{
    private readonly EntityManagerInterface $em;
    private ?array $webhooks = null;

    public function __construct(private readonly EventProducer $eventProducer, EntityManagerInterface $em)
    {
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
        $this->loadWebhooks();

        return array_filter($this->webhooks, fn(Webhook $webhook): bool => $webhook->hasEvent($event));
    }

    private function loadWebhooks(): void
    {
        if (null !== $this->webhooks) {
            return;
        }

        /* @var Webhook[] $webhooks */
        $this->webhooks = $this->em->getRepository(Webhook::class)->findBy([
            'active' => true,
        ]);
    }

    public function hasWebhooks(): bool
    {
        $this->loadWebhooks();

        return !empty($this->webhooks);
    }
}
