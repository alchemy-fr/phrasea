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
        foreach ($message->selectors as $selector) {
            $userIds = $selector->userIds;

            $topic = $selector->topic ?? $message->topic;
            if (null === $topic) {
                throw new \InvalidArgumentException('No subscription topic');
            }

            if ($selector->event) {
                $userIds = array_merge(
                    $userIds,
                    $this->subscriptionManager->getSubscriberUserIds($selector),
                );
            }

            $userIds = array_values(array_unique($userIds));

            if (null !== $message->excludeUserId) {
                $userIds = array_values(array_filter($userIds, static fn (string $id,
                ): bool => $id !== $message->excludeUserId));
            }

            foreach ($userIds as $userId) {
                $this->deliverer->deliver($userId, $topic->topic, $topic->params, $message->options);
                $sent[$userId] = true;
            }
        }
    }
}
