<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notification;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\NotifierInterface as SymfonyNotifierInterface;

final class SymfonyNotifier implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SymfonyNotifierInterface $notifier,
    )
    {
    }

    public function notifyUser(string $userId, string $notificationId, array $parameters = []): void
    {
        $this->logger->debug(sprintf('Send notification to user "%s" with template "%s"', $userId, $notificationId), [
            'parameters' => $parameters,
        ]);

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
