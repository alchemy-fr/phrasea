<?php

declare(strict_types=1);

namespace App\Integration\Auth;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The redirect URI of the OAuth authorization of an integration (see IntegrationAuthController),
 * and whether the redirect URIs of a Keycloak client allow it.
 */
final readonly class OAuthRedirectUriMatcher
{
    /**
     * Stands for an integration not saved yet: the clients allowing every integration allow it.
     */
    private const string ANY_INTEGRATION_ID = '00000000-0000-0000-0000-000000000000';
    private const string INTEGRATIONS_PATH = '/integrations/';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getRedirectUri(?string $integrationId = null): string
    {
        return $this->urlGenerator->generate('integration_auth_code', [
            'integrationId' => $integrationId ?? self::ANY_INTEGRATION_ID,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The redirect URI given to the clients created for the integrations: it allows all of them.
     */
    public function getClientRedirectUri(): string
    {
        $uri = $this->getRedirectUri();
        $pos = strpos($uri, self::INTEGRATIONS_PATH);
        if (false === $pos) {
            throw new \LogicException(sprintf('The redirect URI "%s" is not under "%s"', $uri, self::INTEGRATIONS_PATH));
        }

        return substr($uri, 0, $pos + strlen(self::INTEGRATIONS_PATH)).'*';
    }

    /**
     * The root URL given to the clients created for the integrations: scheme and host of the API.
     */
    public function getClientRootUrl(): string
    {
        $parts = parse_url($this->getRedirectUri());

        return sprintf('%s://%s%s',
            $parts['scheme'] ?? 'https',
            $parts['host'] ?? '',
            isset($parts['port']) ? ':'.$parts['port'] : '',
        );
    }

    /**
     * Whether one of the redirect URIs of a client allows the target URI, the Keycloak way:
     * the same URI, or a prefix of it ending with "*". A relative URI is resolved against
     * the root URL of the client; "+" and "-" (which only make sense for the web origins) allow nothing.
     *
     * @param string[] $redirectUris
     */
    public static function isAllowed(string $target, array $redirectUris, ?string $rootUrl = null): bool
    {
        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || '' === $uri || '+' === $uri[0] || '-' === $uri[0]) {
                continue;
            }

            if (str_starts_with($uri, '/')) {
                if (empty($rootUrl)) {
                    continue;
                }
                $uri = rtrim($rootUrl, '/').$uri;
            }

            if ($uri === $target) {
                return true;
            }

            if (str_ends_with($uri, '*') && str_starts_with($target, substr($uri, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
