<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Tests\Client;

use Alchemy\AuthBundle\Security\AllowedClientIdsProviderInterface;

/**
 * Allows the clients of the tokens forged by KeycloakClientTestMock, whatever ALLOWED_CLIENT_IDS
 * the environment running the tests defines.
 */
final class TestAllowedClientIdsProvider implements AllowedClientIdsProviderInterface
{
    public function isClientAllowed(string $clientId): bool
    {
        return in_array($clientId, [
            KeycloakClientTestMock::CLIENT_ID,
            KeycloakClientTestMock::MACHINE_CLIENT_ID,
        ], true);
    }
}
