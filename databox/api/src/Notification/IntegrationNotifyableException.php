<?php

namespace App\Notification;


use App\Entity\Integration\WorkspaceIntegration;

class IntegrationNotifyableException extends UserNotifyableException
{
    public function __construct(private readonly WorkspaceIntegration $integration, string $subject, string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($this->integration->getWorkspace()?->getOwnerId(), $subject, $message, $code, $previous);
    }

    public function getIntegration(): WorkspaceIntegration
    {
        return $this->integration;
    }
}
