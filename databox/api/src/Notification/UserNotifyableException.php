<?php

declare(strict_types=1);

namespace App\Notification;

use Alchemy\CoreBundle\Exception\IgnoreSentryExceptionInterface;

class UserNotifyableException extends \RuntimeException implements IgnoreSentryExceptionInterface
{
    private array $subscribers = [];
    private string $notificationId = 'user:exception';

    /**
     * Client URI (e.g. `/workspaces/{id}/manage/integrations/{id}`) of the screen where the error can be fixed.
     */
    private ?string $uri = null;

    /**
     * Suffix of the translation key of the action leading to the URI (`user.exception.email.action_{action}`).
     */
    private ?string $action = null;

    /**
     * @var array<string, string> detail key (`user.exception.details.{key}` translation) => value, in display order
     */
    private array $details = [];

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

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return array<string, string>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function addDetail(string $key, ?string $value): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        $this->details[$key] = $value;
    }
}
