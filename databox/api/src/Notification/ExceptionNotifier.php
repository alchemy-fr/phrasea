<?php

namespace App\Notification;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Service\ErrorDisableHandler;

final readonly class ExceptionNotifier
{
    public function __construct(
        private NotifierInterface $notifier,
        private ErrorDisableHandler $errorDisableHandler,
    ) {
    }

    public function notifyException(UserNotifyableException $exception): void
    {
        foreach ($exception->getSubscribers() as $subscriber) {
            $this->notifier->notifyUser($subscriber, $exception->getNotificationId(), [
                'subject' => $exception->getSubject(),
                'message' => $exception->getMessage(),
            ]);
        }

        if ($exception instanceof EntityDisableNotifyableException) {
            $this->errorDisableHandler->handleError($exception->getEntity(), $exception);
        }
    }
}
