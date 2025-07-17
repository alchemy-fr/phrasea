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
        bool $appendWorkspace = true,
    ) {
        $workspace = $this->entity->getWorkspace();

        parent::__construct(
            $this->entity->getOwnerId(),
            $subject,
            $message.($appendWorkspace && $workspace ? ' (in workspace: '.$workspace?->getName().')' : ''),
            $code,
            $previous
        );
    }

    public function getEntity(): ErrorDisableInterface
    {
        return $this->entity;
    }
}
