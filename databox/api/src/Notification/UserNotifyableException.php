<?php

namespace App\Notification;

use Alchemy\CoreBundle\Exception\IgnoreSentryExceptionInterface;

class UserNotifyableException extends \RuntimeException implements IgnoreSentryExceptionInterface
{
    private array $subscribers = [];
    private string $notificationId = 'databox-user-exception';

    public function __construct(?string $userId, private readonly string $subject, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        if ($userId) {
            $this->subscribers[] = $userId;
        }
        parent::__construct($message, $code, $previous);
    }

    public function addSubscribers(array $subscribers): void
    {
        $this->subscribers = array_unique(array_merge($this->subscribers, $subscribers));
    }

    public function getSubscribers(): array
    {
        return $this->subscribers;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function setNotificationId(string $notificationId): void
    {
        $this->notificationId = $notificationId;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}
