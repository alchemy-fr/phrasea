<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\NotifyTopicDto;
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
    public function notifyUsers(array $userIds, string $topic, array $params = [], array $options = []): void
    {
        if (!$this->enabled || [] === $userIds) {
            return;
        }

        $this->topicRegistry->get($topic);

        if ($options['sync'] ?? false) {
            foreach (array_values(array_unique($userIds)) as $userId) {
                $this->deliverer->deliver($userId, $topic, $params, $options);
            }

            return;
        }

        $this->bus->dispatch(new SendNotification(
            topic: $topic,
            userIds: array_values(array_unique($userIds)),
            params: $params,
            options: $options,
        ));
    }

    /**
     * Notify every subscriber following the given object.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options `exclude_user_id` skips a user (e.g. the author)
     */
    public function notifyObject(string $objectType, string $objectId, string $topic, array $params = [], array $options = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->topicRegistry->get($topic);

        $this->bus->dispatch(new SendNotification(
            topic: $topic,
            objectType: $objectType,
            objectId: $objectId,
            params: $params,
            options: $options,
            excludeUserId: $options['exclude_user_id'] ?? null,
        ));
    }

    /**
     * Notify every subscriber following the first met topic.
     *
     * @param NotifyTopicDto[]     $topics
     * @param array<string, mixed> $options `exclude_user_id` skips a user (e.g. the author)
     */
    public function notifyObjects(array $topics, array $params = [], array $options = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->topicRegistry->get($topic);

        $this->bus->dispatch(new SendNotification(
            topic: $topic,
            objectType: $objectType,
            objectId: $objectId,
            params: $params,
            options: $options,
            excludeUserId: $options['exclude_user_id'] ?? null,
        ));
    }
}
