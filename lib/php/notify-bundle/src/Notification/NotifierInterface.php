<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notification;

interface NotifierInterface
{
    public function notifyUser(
        string $userId,
        string $notificationId,
        array $parameters = [],
    ): void;

    public function broadcast(
        string $notificationId,
        array $parameters = [],
    ): void;

    public function sendEmail(
        string $email,
        string $notificationId,
        array $parameters = [],
    ): void;

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void;

    public function addTopicSubscribers(
        string $topicKey,
        array $subscribers,
        bool $direct = false,
    ): void;

    public function createTopic(
        string $topicKey,
    ): void;

    public function removeTopicSubscribers(
        string $topicKey,
        array $subscribers,
    ): void;

    public function getTopicSubscriptions(array $topicKeys, string $userId): array;

    public function getUsername(string $userId): string;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;
}
