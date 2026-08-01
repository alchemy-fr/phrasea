<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists an in-app notification (raw topic + payload) and, when a Pusher
 * integration is available, pushes a real-time event carrying the rendered
 * content so the client can display it without an extra round-trip.
 */
final readonly class InAppChannel implements ChannelInterface
{
    /**
     * The Pusher manager is optional: without it, notifications are still
     * persisted (in-app list / unread badge) but no real-time push is emitted.
     * The renderer is optional too: without it the push omits the rendered
     * subject/content (the client then falls back to the REST API).
     */
    public function __construct(
        private EntityManagerInterface $em,
        private ?PusherManager $pusherManager = null,
        private string $channelPrefix = 'private-user-',
        private string $event = 'notification',
        private ?NotificationRenderer $renderer = null,
    ) {
    }

    public function getType(): ChannelType
    {
        return ChannelType::InApp;
    }

    public function supports(Subscriber $subscriber): bool
    {
        return true;
    }

    public function send(
        Subscriber $subscriber,
        string $topic,
        array $context = [],
        array $options = [],
    ): void {
        $notification = new Notification($subscriber, $topic, $context);

        $this->em->persist($notification);
        $this->em->flush();

        if (null === $this->pusherManager) {
            return;
        }

        // Render the notification now so the pushed payload mirrors what the
        // REST API returns (subject + content + click-through uri). The raw
        // topic/payload are never exposed to the client.
        $rendered = $this->renderer?->render($topic, ChannelType::InApp, $context, $subscriber->getLocale());
        $uri = $rendered?->uri ?? ($context['url'] ?? null);

        $this->pusherManager->trigger(
            $this->channelPrefix.$subscriber->getUserId(),
            $this->event,
            [
                'id' => $notification->getId(),
                'subject' => $rendered?->subject,
                'content' => $rendered?->body,
                'data' => ['uri' => $uri],
                'createdAt' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        );
    }
}
