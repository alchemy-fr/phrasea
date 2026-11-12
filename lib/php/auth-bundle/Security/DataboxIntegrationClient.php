<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

/**
 * Whether a Keycloak OAuth client (its representation in the admin API) is one of the clients of the
 * Databox integrations, whose access tokens Expose accepts: the Expose integration of Databox calls
 * Expose with the tokens its users obtain from such a client.
 *
 * Expose decides it when it receives a token (it reads the client in Keycloak), Databox when it lists
 * the clients an integration can use, so that it can tell the ones Expose will refuse.
 */
final class DataboxIntegrationClient
{
    /**
     * Attribute Databox gives to the clients it creates for its integrations.
     */
    final public const string CREATED_BY_ATTRIBUTE = 'phrasea.created_by';
    final public const string CREATED_BY = 'databox';

    /**
     * Path of the redirect URIs of the OAuth authorization of the integrations, under the Databox API.
     */
    final public const string INTEGRATIONS_PATH = '/integrations/';

    private function __construct()
    {
    }

    /**
     * An enabled client, created by Databox or whose redirect URIs all lead to the Databox integrations.
     *
     * @param string|null $databoxApiUrl the Databox API, as written in the redirect URIs (empty: only the
     *                                   clients created by Databox are accepted)
     */
    public static function isAccepted(array $client, ?string $databoxApiUrl): bool
    {
        if (true !== ($client['enabled'] ?? false)) {
            return false;
        }

        return self::CREATED_BY === ($client['attributes'][self::CREATED_BY_ATTRIBUTE] ?? null)
            || self::hasOnlyDataboxRedirectUris($client, $databoxApiUrl);
    }

    /**
     * A public client with the standard flow only, all of whose redirect URIs are under the integrations
     * path of the Databox API: Keycloak only gives its tokens to Databox, whoever created it.
     * The implicit and "direct access grants" flows are excluded, since they issue tokens without going
     * through a redirect URI (directly to the browser of the user, or to anyone knowing a password).
     *
     * The URIs are compared as strings (a relative URI being resolved against the root URL of the client):
     * a wildcard ("*", "…/integrations*"), another host sharing the prefix of the Databox one, or the
     * "+" and "-" shortcuts of Keycloak do not qualify.
     */
    public static function hasOnlyDataboxRedirectUris(array $client, ?string $databoxApiUrl): bool
    {
        $databoxApiUrl = trim((string) $databoxApiUrl);
        if ('' === $databoxApiUrl) {
            return false;
        }

        if (true !== ($client['publicClient'] ?? false)
            || true !== ($client['standardFlowEnabled'] ?? false)
            || true === ($client['implicitFlowEnabled'] ?? false)
            || true === ($client['directAccessGrantsEnabled'] ?? false)
            || true === ($client['bearerOnly'] ?? false)) {
            return false;
        }

        $redirectUris = $client['redirectUris'] ?? null;
        if (!is_array($redirectUris) || [] === $redirectUris) {
            return false;
        }

        $prefix = rtrim($databoxApiUrl, '/').self::INTEGRATIONS_PATH;
        $rootUrl = $client['rootUrl'] ?? null;
        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || '' === $uri) {
                return false;
            }

            if (str_starts_with($uri, '/')) {
                if (!is_string($rootUrl) || '' === $rootUrl) {
                    return false;
                }
                $uri = rtrim($rootUrl, '/').$uri;
            }

            if (!str_starts_with($uri, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
