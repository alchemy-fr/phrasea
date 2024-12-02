<?php

namespace Alchemy\CoreBundle\Notification;

use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\NotifierInterface;

final readonly class Notifier
{
    public function __construct(
        private NotifierInterface $notifier,
    ) {
    }

    public function sendNotification(string $notificationId, string $userId): void
    {
        $notification = new NovuNotification($notificationId);

        $recipient = new NovuSubscriberRecipient(
            $userId,
            'John',
            'Doe',
            'test@phrasea.local',
        );

        $this->notifier->send($notification, $recipient);
    }
}
