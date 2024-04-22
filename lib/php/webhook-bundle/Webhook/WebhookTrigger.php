<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Consumer\WebhookTriggerMessage;
use Alchemy\WebhookBundle\Entity\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookTrigger
{
    private ?array $webhooks = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    )
    {
    }

    public function triggerEvent(string $event, array $payload): void
    {
        $webhooks = $this->getWebhooksForEvent($event);

        foreach ($webhooks as $webhook) {
            if ($webhook->hasEvent($event)) {
                $this->bus->dispatch(new WebhookTriggerMessage($webhook->getId(), $event, $payload));
            }
        }
    }

    public function getWebhooksForEvent(string $event): array
    {
        $this->loadWebhooks();

        return array_filter($this->webhooks, fn (Webhook $webhook): bool => $webhook->hasEvent($event));
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
