<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class KeycloakClient
{
    public function __construct(
        private HttpClientInterface $keycloakClient,
        private KeycloakUrlGenerator $urlGenerator,
        private CacheInterface $keycloakRealmCache,
        private string $clientId,
        private string $clientSecret,
    ) {
    }

    public function getTokenInfo(string $accessToken): array
    {
        return $this->wrapRequest(function () use ($accessToken) {
            return $this->keycloakClient->request('GET', $this->urlGenerator->getUserinfoUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        });
    }

    public function getTokenFromAuthorizationCode(string $code, string $redirectUri): array
    {
        $data = $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
            ],
        ])->toArray();

        return [$data['access_token'], $data['refresh_token']];
    }

    public function getTokenFromRefreshToken(string $refreshToken): array
    {
        $data = $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => [
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ])->toArray();

        return [$data['access_token'], $data['refresh_token']];
    }


    public function logout(string $accessToken, string $refreshToken): array
    {
        return $this->wrapRequest(function () use ($accessToken, $refreshToken) {
            return $this->keycloakClient->request('POST', $this->urlGenerator->getLogoutUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'body' => [
                    'client_id' => $this->clientId,
                    'refresh_token' => $refreshToken,
                ],
            ]);
        });
    }

    public function createUser(array $data, string $accessToken): array
    {
        try {
            $this->keycloakClient->request('POST', $this->urlGenerator->getUsersApiUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'json' => $data,
            ])->getStatusCode();
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            if (401 === $statusCode) {
                throw new UnauthorizedHttpException('access_token', $e->getResponse()->getContent(false), $e);
            } elseif ($statusCode !== 409) {
                throw $e;
            }
        }

        $users = $this->getUsers($accessToken, 1, null, [
            'query' => [
                'username' => $data['username'],
            ]
        ]);

        if (empty($users)) {
            throw new \RuntimeException(sprintf('Cannot find newly created user. Please check client permissions!'));
        }

        return $users[0];
    }

    public function getUsers(string $accessToken, int $limit = null, int $offset = null, array $options = []): array
    {
        return $this->get($this->urlGenerator->getUsersApiUrl(), $accessToken, $limit, $offset, $options);
    }

    public function getGroups(string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->get($this->urlGenerator->getGroupsApiUrl(), $accessToken, $limit, $offset);
    }

    private function get(string $path, string $accessToken, int $limit = null, int $offset = null, array $options = []): array
    {
        return $this->wrapRequest(function () use ($path, $accessToken, $limit, $offset, $options) {
            return $this->keycloakClient->request('GET', $path, array_merge([
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ], $options));
        });
    }

    private function wrapRequest(callable $handler): array
    {
        try {
            $response = $handler();

            return $response->toArray();
        } catch (ClientException $e) {
            if (401 === $e->getResponse()?->getStatusCode()) {
                throw new UnauthorizedHttpException('access_token', $e->getResponse()->getContent(false), $e);
            }

            throw $e;
        }
    }

    public function getClientCredentialAccessToken(): array
    {
        return $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ])->toArray();
    }

    public function getJwtPublicKey(): string
    {
        return $this->keycloakRealmCache->get('keycloak_public_key', function (ItemInterface $item): string {
            $data = $this->keycloakClient->request('GET', $this->urlGenerator->getRealmInfo())->toArray();

            return $data['public_key'];
        });
    }
}
