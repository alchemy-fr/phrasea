<?php

namespace App\Notification;

use App\Entity\Traits\ErrorDisableInterface;

class EntityDisableNotifyableException extends UserNotifyableException
{
    public function __construct(
        private readonly ErrorDisableInterface $entity,
        string $subject,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $this->entity->getWorkspace()?->getOwnerId(),
            $subject,
            $message,
            $code,
            $previous
        );
    }

    public function getEntity(): ErrorDisableInterface
    {
        return $this->entity;
    }
}
