<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private NotificationDeliverer $deliverer,
        private SubscriptionManager $subscriptionManager,
    ) {
    }

    public function __invoke(SendNotification $message): void
    {
        $sent = [];

        foreach ($message->topics as $topic) {
            $userIds = $topic->userIds;

            if (null !== $topic->objectType && null !== $topic->objectId) {
                $userIds = array_merge(
                    $userIds,
                    $this->subscriptionManager->getSubscriberUserIds($topic->topic, $topic->objectType, $topic->objectId),
                );
            }

            $userIds = array_values(array_unique($userIds));

            if (null !== $message->excludeUserId) {
                $userIds = array_values(array_filter($userIds, static fn (string $id): bool => $id !== $message->excludeUserId));
            }

            foreach ($userIds as $userId) {
                $this->deliverer->deliver($userId, $topic->topic, $message->params, $message->options);
            }
        }
    }
}
