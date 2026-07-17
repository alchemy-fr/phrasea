<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\NotifyOptions;
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
     */
    public function notifyUser(string $userId, string $topic, array $params = [], ?NotifyOptions $options = null): void
    {
        $this->notifyUsers([$userId], $topic, $params, $options);
    }

    /**
     * @param array<int, string>   $userIds
     * @param array<string, mixed> $params
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
     *
     * @param array<int, NotifySelectorDto> $selectors
     */
    public function notify(array $selectors, ?NotifyOptions $options = null): void
    {
        if (!$this->enabled || [] === $selectors) {
            return;
        }

        // Fail fast on an unknown topic, rather than in the worker
        foreach ($selectors as $selector) {
            if (null !== $selector->topic) {
                $this->topicRegistry->get($selector->topic->topic);
            }
        }

        $this->bus->dispatch(new SendNotification(
            selectors: $selectors,
            excludeUserId: $options?->excludeUserId,
        ));
    }
}
