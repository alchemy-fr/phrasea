<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

use Symfony\Component\DependencyInjection\Attribute\Target;
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

    public function ensureGroup(string $accessToken, string $name, ?string $parentId = null, array $attributes = [], bool $adopt = false): array
    {
        $existing = $this->findGroupByName($accessToken, $name, $parentId);
        if (null === $existing) {
            $url = null === $parentId
                ? $this->urlGenerator->getGroupsApiUrl()
                : sprintf('%s/%s/children', $this->urlGenerator->getGroupsApiUrl(), $parentId);

            try {
                $this->write('POST', $url, $accessToken, [
                    'name' => $name,
                    'attributes' => $this->toGroupAttributes($attributes),
                ]);
            } catch (HttpException $e) {
                if (409 !== $e->getStatusCode()) {
                    throw $e;
                }
            }

            $existing = $this->findGroupByName($accessToken, $name, $parentId)
                ?? throw new \RuntimeException(sprintf('Cannot find newly created group "%s". Please check client permissions!', $name));
        }

        if ($this->hasGroupAttributes($existing, $attributes)) {
            return $existing;
        }

        if (!$adopt) {
            throw new GroupOwnershipException($existing['path'] ?? $name);
        }

        $existing['attributes'] = array_merge($existing['attributes'] ?? [], $this->toGroupAttributes($attributes));
        $this->write('PUT', sprintf('%s/%s', $this->urlGenerator->getGroupsApiUrl(), $existing['id']), $accessToken, [
            'name' => $existing['name'],
            'attributes' => $existing['attributes'],
        ]);

        return $existing;
    }

    public function findClientByClientId(string $accessToken, string $clientId): ?array
    {
        foreach ($this->get($this->urlGenerator->getClientsApiUrl(), [
            'access_token' => $accessToken,
            'query' => [
                'clientId' => $clientId,
            ],
        ]) as $client) {
            if (($client['clientId'] ?? null) === $clientId) {
                return $client;
            }
        }

        return null;
    }

    public function createClient(string $accessToken, array $data): void
    {
        $this->write('POST', $this->urlGenerator->getClientsApiUrl(), $accessToken, $data);
    }

    public function updateClient(string $accessToken, string $id, array $data): void
    {
        $this->write('PUT', sprintf('%s/%s', $this->urlGenerator->getClientsApiUrl(), $id), $accessToken, $data);
    }

    public function findGroup(string $accessToken, string $name, ?string $parentId = null): ?array
    {
        return $this->findGroupByName($accessToken, $name, $parentId);
    }

    public function getGroupChildren(string $accessToken, string $groupId): array
    {
        return $this->get(sprintf('%s/%s/children', $this->urlGenerator->getGroupsApiUrl(), $groupId), [
            'access_token' => $accessToken,
            'query' => [
                'briefRepresentation' => 'false',
                'max' => 1000,
            ],
        ]);
    }

    public function deleteGroup(string $accessToken, string $groupId): void
    {
        $this->write('DELETE', sprintf('%s/%s', $this->urlGenerator->getGroupsApiUrl(), $groupId), $accessToken);
    }

    public function searchClients(string $accessToken, string $clientIdPrefix): array
    {
        return array_values(array_filter(
            $this->get($this->urlGenerator->getClientsApiUrl(), [
                'access_token' => $accessToken,
                'query' => [
                    'clientId' => $clientIdPrefix,
                    'search' => 'true',
                    'max' => 1000,
                ],
            ]),
            fn (array $client): bool => str_starts_with($client['clientId'] ?? '', $clientIdPrefix),
        ));
    }

    public function deleteClient(string $accessToken, string $clientUuid): void
    {
        $this->write('DELETE', sprintf('%s/%s', $this->urlGenerator->getClientsApiUrl(), $clientUuid), $accessToken);
    }

    public function getClientRoles(string $accessToken, string $clientUuid): array
    {
        return $this->get(sprintf('%s/%s/roles', $this->urlGenerator->getClientsApiUrl(), $clientUuid), [
            'access_token' => $accessToken,
        ]);
    }

    public function deleteClientRole(string $accessToken, string $clientUuid, string $roleName): void
    {
        $this->write('DELETE', sprintf('%s/%s/roles/%s', $this->urlGenerator->getClientsApiUrl(), $clientUuid, rawurlencode($roleName)), $accessToken);
    }

    public function getServiceAccountUser(string $accessToken, string $clientUuid): array
    {
        return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('clients/%s/service-account-user', $clientUuid)), [
            'access_token' => $accessToken,
        ]);
    }

    public function findRealmRole(string $accessToken, string $roleName): ?array
    {
        try {
            return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('roles/%s', rawurlencode($roleName))), [
                'access_token' => $accessToken,
            ]);
        } catch (HttpException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    public function findClientRole(string $accessToken, string $clientUuid, string $roleName): ?array
    {
        try {
            return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('clients/%s/roles/%s', $clientUuid, rawurlencode($roleName))), [
                'access_token' => $accessToken,
            ]);
        } catch (HttpException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    public function getUserRealmRoleMappings(string $accessToken, string $userId): array
    {
        return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('users/%s/role-mappings/realm/composite', $userId)), [
            'access_token' => $accessToken,
        ]);
    }

    public function getUserClientRoleMappings(string $accessToken, string $userId, string $clientUuid): array
    {
        return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('users/%s/role-mappings/clients/%s/composite', $userId, $clientUuid)), [
            'access_token' => $accessToken,
        ]);
    }

    public function addUserRealmRoles(string $accessToken, string $userId, array $roles): void
    {
        $this->write('POST', $this->urlGenerator->getAdminApiUrl(sprintf('users/%s/role-mappings/realm', $userId)), $accessToken, $roles);
    }

    public function addUserClientRoles(string $accessToken, string $userId, string $clientUuid, array $roles): void
    {
        $this->write('POST', $this->urlGenerator->getAdminApiUrl(sprintf('users/%s/role-mappings/clients/%s', $userId, $clientUuid)), $accessToken, $roles);
    }

    public function getClientScopes(string $accessToken): array
    {
        return $this->get($this->urlGenerator->getAdminApiUrl('client-scopes'), [
            'access_token' => $accessToken,
        ]);
    }

    public function getClientAssignedScopes(string $accessToken, string $clientUuid, string $type): array
    {
        return $this->get($this->urlGenerator->getAdminApiUrl(sprintf('clients/%s/%s-client-scopes', $clientUuid, $type)), [
            'access_token' => $accessToken,
        ]);
    }

    public function addClientOptionalScope(string $accessToken, string $clientUuid, string $scopeId): void
    {
        $this->write('PUT', $this->urlGenerator->getAdminApiUrl(sprintf('clients/%s/optional-client-scopes/%s', $clientUuid, $scopeId)), $accessToken);
    }

    public function addUserToGroup(string $accessToken, string $userId, string $groupId): void
    {
        $this->write('PUT', sprintf('%s/%s/groups/%s', $this->urlGenerator->getUsersApiUrl(), $userId, $groupId), $accessToken);
    }

    public function removeUserFromGroup(string $accessToken, string $userId, string $groupId): void
    {
        $this->write('DELETE', sprintf('%s/%s/groups/%s', $this->urlGenerator->getUsersApiUrl(), $userId, $groupId), $accessToken);
    }

    private function write(string $method, string $url, string $accessToken, ?array $data = null): void
    {
        $response = $this->keycloakClient->request($method, $url, $this->getRequestOptions([
            'access_token' => $accessToken,
            ...(null !== $data ? ['json' => $data] : []),
        ]));
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300) {
            throw match ($statusCode) {
                403 => new AccessDeniedHttpException($response->getContent(false)),
                default => new HttpException($statusCode, $response->getContent(false)),
            };
        }
    }

    private function findGroupByName(string $accessToken, string $name, ?string $parentId): ?array
    {
        if (null === $parentId) {
            $candidates = $this->get($this->urlGenerator->getGroupsApiUrl(), [
                'access_token' => $accessToken,
                'query' => [
                    'search' => $name,
                    'exact' => 'true',
                    'briefRepresentation' => 'false',
                ],
            ]);
            $expectedPath = '/'.$name;
        } else {
            $candidates = $this->get(sprintf('%s/%s/children', $this->urlGenerator->getGroupsApiUrl(), $parentId), [
                'access_token' => $accessToken,
                'query' => [
                    'briefRepresentation' => 'false',
                    'max' => 1000,
                ],
            ]);
            $expectedPath = null;
        }

        foreach ($candidates as $group) {
            if (($group['name'] ?? null) === $name && (null === $expectedPath || ($group['path'] ?? null) === $expectedPath)) {
                return $group;
            }
        }

        return null;
    }

    private function toGroupAttributes(array $attributes): array
    {
        return array_map(fn (string $value): array => [$value], $attributes);
    }

    private function hasGroupAttributes(array $group, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if (($group['attributes'][$key][0] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Direct members of the group (not the members of its subgroups).
     */
    public function getGroupMembers(string $accessToken, string $groupId, int $first, int $max): array
    {
        return $this->get(sprintf('%s/%s/members', $this->urlGenerator->getGroupsApiUrl(), $groupId), [
            'access_token' => $accessToken,
            'query' => [
                'briefRepresentation' => 'true',
                'first' => $first,
                'max' => $max,
            ],
        ]);
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
