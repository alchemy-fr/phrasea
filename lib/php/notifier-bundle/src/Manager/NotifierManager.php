<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Public entry point of the bundle: dispatch topic notifications to users or
 * to the followers of an object.
 */
final readonly class NotifierManager
{
    public function __construct(
        private MessageBusInterface $bus,
        private NotificationDeliverer $deliverer,
        private TopicRegistry $topicRegistry,
        private bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function notifyUser(string $userId, string $topic, array $params = [], array $options = []): void
    {
        $this->notifyUsers([$userId], $topic, $params, $options);
    }

    /**
     * @param array<int, string>   $userIds
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function notifyUsers(array $userIds, string $topic, array $params = [], ?NotifyOptions $options = null): void
    {
        $this->notify(
            [
                new NotifySelectorDto(
                    userIds: $userIds,
                    topic: new TopicDto(
                        $topic,
                        $params,
                    ),
                ),
            ],
            $options,
        );
    }

    /**
     * Notify by selectors.
     */
    public function notify(array $selectors, ?NotifyOptions $options = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->bus->dispatch(new SendNotification(
            selectors: $selectors,
            options: $options,
            excludeUserId: $options?->excludeUserId,
        ));
    }
}
