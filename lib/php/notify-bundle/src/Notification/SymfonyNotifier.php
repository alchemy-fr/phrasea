<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notification;

use Alchemy\NotifyBundle\Service\NovuClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\NotifierInterface as SymfonyNotifierInterface;

final class SymfonyNotifier implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SymfonyNotifierInterface $notifier,
        private readonly NovuClient $novuClient,
    )
    {
    }

    public function notifyUser(string $userId, string $notificationId, array $parameters = []): void
    {
        $recipient = new NovuSubscriberRecipient($userId);
        $this->sendNotification($recipient, $notificationId, $parameters, 'user');
    }

    public function sendEmail(string $email, string $notificationId, array $parameters = []): void
    {
        $recipient = new NovuSubscriberRecipient($email, email: $email);
        $this->sendNotification($recipient, $notificationId, $parameters);
    }

    private function sendNotification(NovuSubscriberRecipient $recipient, string $notificationId, array $parameters): void
    {
        $content = json_encode($parameters, JSON_THROW_ON_ERROR);
        $this->logger->debug(sprintf('Send notification "%s" with template "%s"', $recipient->getSubscriberId(), $notificationId), [
            'content' => $content,
        ]);

        $notification = new NovuNotification($notificationId);
        $notification->content($content);

        $this->notifier->send($notification, $recipient);
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
    ): void {
        $this->novuClient->notifyTopic($topicKey, $authorId, $notificationId, $parameters);
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->novuClient->addTopicSubscribers($topicKey, $subscribers);
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->novuClient->removeTopicSubscribers($topicKey, $subscribers);
    }
}
