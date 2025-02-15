<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notification;

final class MockNotifier implements NotifierInterface
{
    private array $sentNotifications = [];
    private array $notifiedTopics = [];

    public function notifyUser(string $userId, string $notificationId, array $parameters = []): void
    {
        $this->sentNotifications[] = [
            'userId' => $userId,
            'notificationId' => $notificationId,
            'parameters' => $parameters,
        ];
    }

    public function broadcast(string $notificationId, array $parameters = []): void
    {
        $this->sentNotifications[] = [
            'userId' => '*',
            'notificationId' => $notificationId,
            'parameters' => $parameters,
        ];
    }

    public function sendEmail(string $email, string $notificationId, array $parameters = []): void
    {
        $this->sentNotifications[] = [
            'email' => $email,
            'notificationId' => $notificationId,
            'parameters' => $parameters,
        ];
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        $this->notifiedTopics[] = [
            'topicKey' => $topicKey,
            'authorId' => $authorId,
            'notificationId' => $notificationId,
            'parameters' => $parameters,
            'options' => $parameters,
        ];
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers): void
    {
    }

    public function createTopic(string $topicKey): void
    {
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
    }

    public function getTopicSubscriptions(array $topicKeys, string $userId): array
    {
        return [];
    }

    public function getUsername(string $userId): string
    {
        return $userId;
    }

    public function getSentNotifications(): array
    {
        return $this->sentNotifications;
    }

    public function getNotifiedTopics(): array
    {
        return $this->notifiedTopics;
    }
}
