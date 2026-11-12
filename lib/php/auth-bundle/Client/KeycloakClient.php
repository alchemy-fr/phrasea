<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

use Alchemy\AuthBundle\Exception\ClientAlreadyExistsException;
use Alchemy\AuthBundle\Exception\GroupAlreadyExistsException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class KeycloakClient
{
    public function __construct(
        #[Target]
        private HttpClientInterface $keycloakClient,
        private KeycloakUrlGenerator $urlGenerator,
        #[Target]
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
            'query' => [
                'username' => $data['username'],
                'exact' => 'true',
                'max' => 1,
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
            }, nonHandledCodes: [404]);
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
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

    public function getUsersByIds(string $accessToken, array $ids, array $options = []): array
    {
        $users = $this->get($this->urlGenerator->getUsersApiUrl(), [
            ...$options,
            'access_token' => $accessToken,
            'query' => [
                'search' => 'id:'.implode(' ', $ids),
                ...$options['query'] ?? [],
            ],
        ]);

        $indexedUsers = [];
        foreach ($users as $user) {
            $indexedUsers[$user['id']] = $user;
        }

        return $indexedUsers;
    }

    /**
     * Effective realm roles of the user (composites and group roles included).
     */
    public function getUserEffectiveRealmRoles(string $accessToken, string $userId): array
    {
        return $this->get(sprintf('%s/%s/role-mappings/realm/composite', $this->urlGenerator->getUsersApiUrl(), $userId), [
            'access_token' => $accessToken,
            'query' => [
                'briefRepresentation' => 'true',
            ],
        ]);
    }

    public function getUserGroups(string $accessToken, string $userId): array
    {
        return $this->get(sprintf('%s/%s/groups', $this->urlGenerator->getUsersApiUrl(), $userId), [
            'access_token' => $accessToken,
            'query' => [
                'briefRepresentation' => 'true',
                'max' => 1000,
            ],
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
            }, nonHandledCodes: [404]);
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Direct members of the group (not the members of its subgroups), in their brief representation.
     * With a user's token, it needs the "view-users" or "manage-users" role of "realm-management".
     */
    public function getGroupMembers(string $accessToken, string $groupId, int $first, int $max): array
    {
        return $this->asTokenOwner(fn (): array => $this->get($this->getGroupUrl($groupId).'/members', [
            'access_token' => $accessToken,
            'query' => [
                'briefRepresentation' => 'true',
                'first' => $first,
                'max' => $max,
            ],
        ]));
    }

    /**
     * Creates a top level group of the realm on behalf of the owner of the access token
     * ("manage-users" role of "realm-management").
     *
     * @param array $data a Keycloak "GroupRepresentation"
     *
     * @return array the created group, as Keycloak returns it
     *
     * @throws GroupAlreadyExistsException
     */
    public function createGroup(array $data, string $accessToken): array
    {
        $response = $this->sendAsTokenOwner('POST', $this->urlGenerator->getGroupsApiUrl(), $accessToken, [
            'json' => $data,
        ], fn (): \Throwable => new GroupAlreadyExistsException((string) ($data['name'] ?? '')));

        $location = $response->getHeaders(false)['location'][0] ?? '';
        $groupId = basename((string) parse_url($location, PHP_URL_PATH));
        if ('' === $groupId || null === $group = $this->getGroup($accessToken, $groupId)) {
            throw new \RuntimeException('Cannot find newly created group. Please check user permissions!');
        }

        return $group;
    }

    /**
     * Updates a group of the realm on behalf of the owner of the access token ("manage-users" role
     * of "realm-management"). Keycloak only changes the properties of the representation given.
     *
     * @param array $data a partial Keycloak "GroupRepresentation"
     *
     * @throws GroupAlreadyExistsException
     */
    public function updateGroup(string $groupId, array $data, string $accessToken): void
    {
        $this->sendAsTokenOwner('PUT', $this->getGroupUrl($groupId), $accessToken, [
            'json' => $data,
        ], fn (): \Throwable => new GroupAlreadyExistsException((string) ($data['name'] ?? '')));
    }

    /**
     * Deletes a group of the realm (and its subgroups) on behalf of the owner of the access token
     * ("manage-users" role of "realm-management").
     */
    public function deleteGroup(string $groupId, string $accessToken): void
    {
        $this->sendAsTokenOwner('DELETE', $this->getGroupUrl($groupId), $accessToken);
    }

    /**
     * Makes a user a direct member of a group, on behalf of the owner of the access token
     * ("manage-users" role of "realm-management"). Nothing changes for a member already.
     */
    public function addUserToGroup(string $userId, string $groupId, string $accessToken): void
    {
        $this->sendAsTokenOwner('PUT', $this->getUserGroupUrl($userId, $groupId), $accessToken);
    }

    /**
     * Removes a user from the direct members of a group, on behalf of the owner of the access token
     * ("manage-users" role of "realm-management").
     */
    public function removeUserFromGroup(string $userId, string $groupId, string $accessToken): void
    {
        $this->sendAsTokenOwner('DELETE', $this->getUserGroupUrl($userId, $groupId), $accessToken);
    }

    private function getGroupUrl(string $groupId): string
    {
        return sprintf('%s/%s', $this->urlGenerator->getGroupsApiUrl(), rawurlencode($groupId));
    }

    private function getUserGroupUrl(string $userId, string $groupId): string
    {
        return sprintf('%s/%s/groups/%s', $this->urlGenerator->getUsersApiUrl(), rawurlencode($userId), rawurlencode($groupId));
    }

    /**
     * Sends a write request of the admin API authenticated by a user's token. As for asTokenOwner(),
     * Keycloak refusing that token is a missing permission.
     *
     * @param (callable(): \Throwable)|null $onConflict the exception of a 409 answer
     */
    private function sendAsTokenOwner(string $method, string $url, string $accessToken, array $options = [], ?callable $onConflict = null): ResponseInterface
    {
        $response = $this->keycloakClient->request($method, $url, $this->getRequestOptions([
            ...$options,
            'access_token' => $accessToken,
        ]));

        $statusCode = $response->getStatusCode();
        if (409 === $statusCode && null !== $onConflict) {
            throw $onConflict();
        }

        return match (true) {
            401 === $statusCode, 403 === $statusCode => throw new AccessDeniedHttpException($response->getContent(false)),
            404 === $statusCode => throw new NotFoundHttpException($response->getContent(false)),
            $statusCode >= 300 => throw new HttpException($statusCode, $response->getContent(false)),
            default => $response,
        };
    }

    /**
     * OAuth clients of the realm (Keycloak "ClientRepresentation"s), as the owner of the access token
     * is allowed to see them ("view-clients" role of "realm-management").
     */
    public function getClients(string $accessToken, array $options = []): array
    {
        return $this->asTokenOwner(fn (): array => $this->get($this->urlGenerator->getClientsApiUrl(), [
            ...$options,
            'access_token' => $accessToken,
        ]));
    }

    /**
     * Creates an OAuth client of the realm on behalf of the owner of the access token
     * ("manage-clients" role of "realm-management").
     *
     * @param array $data a Keycloak "ClientRepresentation"
     *
     * @return array the created client, as Keycloak returns it
     *
     * @throws ClientAlreadyExistsException
     */
    public function createClient(array $data, string $accessToken): array
    {
        $response = $this->keycloakClient->request('POST', $this->urlGenerator->getClientsApiUrl(), $this->getRequestOptions([
            'access_token' => $accessToken,
            'json' => $data,
        ]));

        $statusCode = $response->getStatusCode();
        if (409 === $statusCode) {
            throw new ClientAlreadyExistsException((string) $data['clientId']);
        }
        if (401 === $statusCode || 403 === $statusCode) {
            throw new AccessDeniedHttpException($response->getContent(false));
        }
        if ($statusCode >= 300) {
            throw new HttpException($statusCode, $response->getContent(false));
        }

        $clients = $this->getClients($accessToken, [
            'query' => [
                'clientId' => $data['clientId'],
                'search' => 'false',
                'max' => 1,
            ],
        ]);

        if (empty($clients)) {
            throw new \RuntimeException('Cannot find newly created client. Please check user permissions!');
        }

        return $clients[0];
    }

    /**
     * Adds a client scope of the realm to the default scopes of an OAuth client, on behalf of the owner
     * of the access token ("manage-clients" role of "realm-management"). Nothing changes for a scope
     * already there.
     *
     * @param string $clientUuid the ID of the client in Keycloak ("id"), not its "clientId"
     *
     * @throws \RuntimeException when the realm has no such scope
     */
    public function addDefaultClientScope(string $clientUuid, string $scopeName, string $accessToken): void
    {
        $scopeId = null;
        foreach ($this->asTokenOwner(fn (): array => $this->get($this->urlGenerator->getClientScopesApiUrl(), [
            'access_token' => $accessToken,
        ])) as $scope) {
            if ($scopeName === ($scope['name'] ?? null)) {
                $scopeId = (string) $scope['id'];
                break;
            }
        }

        if (null === $scopeId) {
            throw new \RuntimeException(sprintf('The client scope "%s" does not exist in the realm: the Keycloak configuration of Phrasea was not applied (run bin/migrate.sh)', $scopeName));
        }

        $this->sendAsTokenOwner('PUT', sprintf(
            '%s/%s/default-client-scopes/%s',
            $this->urlGenerator->getClientsApiUrl(),
            rawurlencode($clientUuid),
            rawurlencode($scopeId),
        ), $accessToken);
    }

    /**
     * Runs a request of the admin API authenticated by a user's token. Keycloak refusing that token
     * is a missing permission, not the end of the user's session in the application.
     */
    private function asTokenOwner(callable $handler): array
    {
        try {
            return $handler();
        } catch (HttpException $e) {
            if (401 === $e->getStatusCode()) {
                throw new AccessDeniedHttpException($e->getMessage(), $e);
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

        return $requestOptions;
    }

    private function wrapRequest(callable $handler, array $nonHandledCodes = []): array
    {
        try {
            $response = $handler();

            return $response->toArray();
        } catch (HttpExceptionInterface $e) {
            if (null !== $statusCode = $e->getResponse()?->getStatusCode()) {
                if (in_array($statusCode, $nonHandledCodes, true)) {
                    throw $e;
                }

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
            $data = $this->keycloakClient->request('GET', $this->urlGenerator->getRealmInfoUrl(true))->toArray();

            return $data['public_key'];
        });
    }
}
