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
        $content = json_encode($parameters, JSON_THROW_ON_ERROR);
        $this->logger->debug(sprintf('Send notification to user "%s" with template "%s"', $userId, $notificationId), [
            'content' => $content,
        ]);

        $notification = new NovuNotification($notificationId);
        $notification->content($content);

        $recipient = new NovuSubscriberRecipient($userId);

        $this->notifier->send($notification, $recipient);
    }
}
