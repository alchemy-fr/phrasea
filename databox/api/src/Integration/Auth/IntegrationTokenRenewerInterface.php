<?php

declare(strict_types=1);

namespace App\Integration\Auth;

use App\Integration\IntegrationConfig;

/**
 * Implemented by integrations whose tokens are obtained through an OAuth
 * refresh_token grant, so that the tokens they persist can be renewed
 * outside of a request (cron) before they expire.
 */
interface IntegrationTokenRenewerInterface
{
    /**
     * @return array The raw token response (access_token, refresh_token, expires_in, ...)
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface when the refresh token is rejected
     */
    public function renewIntegrationToken(IntegrationConfig $config, string $refreshToken): array;
}
