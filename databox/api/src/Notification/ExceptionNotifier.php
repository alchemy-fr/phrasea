<?php

namespace App\Notification;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Integration\IntegrationManager;

final readonly class ExceptionNotifier
{
    public function __construct(
        private NotifierInterface $notifier,
        private IntegrationManager $integrationManager,
    )
    {
    }

    public function notifyException(UserNotifyableException $exception): void
    {
        foreach ($exception->getSubscribers() as $subscriber) {
            $this->notifier->notifyUser($subscriber, $exception->getNotificationId(), [
                'subject' => $exception->getSubject(),
                'message' => $exception->getMessage(),
            ]);
        }

        if ($exception instanceof IntegrationNotifyableException) {
            $this->integrationManager->appendError($exception->getIntegration(), $exception);
        }
    }
}

