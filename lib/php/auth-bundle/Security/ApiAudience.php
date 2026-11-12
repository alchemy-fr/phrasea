<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

/**
 * An API accepts the access tokens whose audience ("aud" claim) includes its admin client
 * (ADMIN_CLIENT_ID): Keycloak adds it to the tokens of the clients having the client scope of the
 * API, whose audience mapper names that client (see the Keycloak configurator).
 */
final class ApiAudience
{
    private function __construct()
    {
    }

    /**
     * The Keycloak client scope giving the audience of the API of an application ("databox-api"…).
     *
     * @param string $app the name of the application ("databox", "expose", "uploader")
     */
    public static function getScopeName(string $app): string
    {
        return $app.'-api';
    }
}
