<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Repository\SubscriberRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BroadcastNotificationHandler
{
    public function __construct(
        private SubscriberRepository $subscriberRepository,
        private NotificationDeliverer $deliverer,
    ) {
    }

    public function __invoke(BroadcastNotification $message): void
    {
        foreach ($this->subscriberRepository->iterateUserIds() as $userId) {
            $this->deliverer->deliver($userId, $message->topic, $message->params);
        }
    }
}
