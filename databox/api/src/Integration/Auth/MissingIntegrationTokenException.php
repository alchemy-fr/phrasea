<?php

declare(strict_types=1);

namespace App\Integration\Auth;

use App\Notification\UserNotifyableException;

/**
 * No usable token remains for a user on an integration (expired, revoked or
 * deleted). The user has to authenticate again; retrying the job is useless.
 */
final class MissingIntegrationTokenException extends UserNotifyableException
{
    public function __construct(
        ?string $userId,
        string $integrationName,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $userId,
            sprintf('%s: authentication required', $integrationName),
            $message,
            previous: $previous,
        );
    }
}
