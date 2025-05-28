<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
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
            return $this->keycloakClient->request('GET', $this->urlGenerator->getUserinfoUrl(), $this->getRequestOptions([
                'access_token' => $accessToken,
            ]));
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
            return $this->keycloakClient->request('POST', $this->urlGenerator->getLogoutUrl(), $this->getRequestOptions([
                'access_token' => $accessToken,
                'body' => [
                    'client_id' => $this->clientId,
                    'refresh_token' => $refreshToken,
                ],
            ]));
        });
    }

    public function createUser(array $data, string $accessToken): array
    {
        try {
            $this->keycloakClient->request('POST', $this->urlGenerator->getUsersApiUrl(), $this->getRequestOptions([
                'access_token' => $accessToken,
                'json' => $data,
            ]))->getStatusCode();
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            if (401 === $statusCode) {
                throw new UnauthorizedHttpException('access_token', $e->getResponse()->getContent(false), $e);
            } elseif (409 !== $statusCode) {
                throw $e;
            }
        }

        $users = $this->getUsers($accessToken, [
            'limit' => 1,
            'query' => [
                'username' => $data['username'],
            ],
        ]);

        if (empty($users)) {
            throw new \RuntimeException('Cannot find newly created user. Please check client permissions!');
        }

        return $users[0];
    }

    public function getUser(string $accessToken, string $userId, array $options = []): ?array
    {
        try {
            return $this->wrapRequest(function () use ($userId, $accessToken, $options) {
                return $this->keycloakClient->request('GET', sprintf('%s/%s', $this->urlGenerator->getUsersApiUrl(), $userId), $this->getRequestOptions([
                    ...$options,
                    'access_token' => $accessToken,
                ]));
            });
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    public function getUsers(string $accessToken, array $options = []): array
    {
        return $this->get($this->urlGenerator->getUsersApiUrl(), [
            ...$options,
            'access_token' => $accessToken,
        ]);
    }

    public function getGroups(string $accessToken, array $options = []): array
    {
        return $this->get($this->urlGenerator->getGroupsApiUrl(), [
            ...$options,
            'access_token' => $accessToken,
        ]);
    }

    public function getGroup(string $accessToken, string $groupId, array $options = []): ?array
    {
        try {
            return $this->wrapRequest(function () use ($groupId, $accessToken, $options) {
                return $this->keycloakClient->request('GET', sprintf('%s/%s', $this->urlGenerator->getGroupsApiUrl(), $groupId), $this->getRequestOptions(array_merge($options, [
                    'access_token' => $accessToken,
                ])));
            });
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    private function get(string $path, array $options = []): array
    {
        return $this->wrapRequest(function () use ($path, $options) {
            return $this->keycloakClient->request('GET', $path, $this->getRequestOptions($options));
        });
    }

    private function getRequestOptions(array $options): array
    {
        $requestOptions = [];

        foreach ([
            'headers',
            'query',
            'json',
            'body',
        ] as $key) {
            if (isset($options[$key])) {
                $requestOptions[$key] = $options[$key];
            }
        }

        if (isset($options['access_token'])) {
            $requestOptions['headers'] ??= [];
            $requestOptions['headers']['Authorization'] = 'Bearer '.$options['access_token'];
        }

        foreach ([
            'limit',
            'offset',
        ] as $key) {
            if (isset($options[$key])) {
                $requestOptions['query'] ??= [];
                $requestOptions['query'][$key] = $options[$key];
            }
        }

        return $requestOptions;
    }

    private function wrapRequest(callable $handler): array
    {
        try {
            $response = $handler();

            return $response->toArray();
        } catch (ClientException $e) {
            if (null !== $statusCode = $e->getResponse()?->getStatusCode()) {
                throw match ($statusCode) {
                    403 => new AccessDeniedHttpException($e->getResponse()->getContent(false), $e),
                    default => new HttpException($statusCode, $e->getResponse()->getContent(false), $e),
                };
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
