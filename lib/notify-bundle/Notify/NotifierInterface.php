<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notify;


interface NotifierInterface
{
    public function sendEmail(string $email, string $template, string $locale, array $parameters = []): void;

    public function notifyUser(
        string $userId,
        string $template,
        array $parameters = [],
        array $contactInfo = null
    ): void;

    public function registerUser(string $userId, array $contactInfo): void;

    public function deleteUser(string $userId): void;

    public function notifyTopic(string $topic, string $template, array $parameters = []): void;
}
