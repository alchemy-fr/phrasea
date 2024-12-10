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
}
