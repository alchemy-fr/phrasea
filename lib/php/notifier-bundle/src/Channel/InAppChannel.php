<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists an in-app notification (raw topic + payload, rendered lazily at read
 * time) and, when a Pusher integration is available, pushes a real-time event.
 */
final readonly class InAppChannel implements ChannelInterface
{
    /**
     * The Pusher manager is optional: without it, notifications are still
     * persisted (in-app list / unread badge) but no real-time push is emitted.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private ?PusherManager $pusherManager = null,
        private string $channelPrefix = 'private-user-',
        private string $event = 'notification',
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

        // Only the id + click-through uri are pushed; the raw topic/payload are
        // never exposed to the client. Rendered content comes from the API.
        $this->pusherManager?->trigger(
            $this->channelPrefix.$subscriber->getUserId(),
            $this->event,
            [
                'id' => $notification->getId(),
                'data' => ['uri' => $context['url'] ?? null],
                'createdAt' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        );
    }
}
