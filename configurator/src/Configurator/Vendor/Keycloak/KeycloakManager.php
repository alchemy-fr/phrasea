<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

use App\Util\HttpClientUtil;
use App\Util\UriTemplate;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class KeycloakManager
{
    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $keycloakClient,
        private readonly string $keycloakRealm,
    ) {
        if ('master' === $this->keycloakRealm) {
            throw new \LogicException('Your Keycloak Realm cannot be named "master".');
        }
    }

    private function getAuthenticatedClient(): HttpClientInterface
    {
        return $this->keycloakClient
            ->withOptions([
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getToken(),
                ],
            ]);
    }

    private function getToken(): string
    {
        if (null !== $this->token) {
            return $this->token;
        }

        $response = $this->keycloakClient->request('POST', UriTemplate::resolve('/realms/{realm}/protocol/openid-connect/token', [
            'realm' => 'master',
        ]), [
            'base_uri' => getenv('KEYCLOAK_URL'),
            'body' => [
                'client_id' => 'admin-cli',
                'grant_type' => 'password',
                'username' => getenv('KEYCLOAK_ADMIN'),
                'password' => getenv('KEYCLOAK_ADMIN_PASSWORD'),
            ],
        ]);

        return $this->token = $response->toArray()['access_token'];
    }

    public function createRealm(): void
    {
        $data = [
            'realm' => $this->keycloakRealm,
            'enabled' => true,
        ];
        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()->request('POST', '', [
            'json' => $data,
        ])->getContent(), 409, $data);
    }

    protected function getClients(?string $realm = null): array
    {
        return $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/clients', [
            'realm' => $realm ?? $this->keycloakRealm,
        ]))->toArray();
    }

    public function getClientByClientId(string $clientId, ?string $realm = null): ?array
    {
        $clients = $this->getClients($realm);
        foreach ($clients as $client) {
            if ($clientId === $client['clientId']) {
                return $client;
            }
        }

        return null;
    }

    public function getDefaultClientScopes(): array
    {
        return $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/default-default-client-scopes', [
            'realm' => $this->keycloakRealm,
        ]))->toArray();
    }

    public function getDefaultClientScopesByName(string $name): ?array
    {
        $scopes = $this->getDefaultClientScopes();
        foreach ($scopes as $scope) {
            if ('roles' === $scope['name']) {
                return $scope;
            }
        }

        return null;
    }

    public function getClientScope(string $id): array
    {
        return $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/client-scopes/{id}', [
            'realm' => $this->keycloakRealm,
            'id' => $id,
        ]))->toArray();
    }

    public function getClientScopeProtocolMapperByName(string $scopeId, string $protocolName): ?array
    {
        $protocolMappers = $this->getClientScope($scopeId)['protocolMappers'];
        foreach ($protocolMappers as $protocolMapper) {
            if ($protocolName === $protocolMapper['name']) {
                return $protocolMapper;
            }
        }

        return null;
    }

    public function putClientScopeProtocolMapper(string $scopeId, string $mapperId, array $data): void
    {
        $this->getAuthenticatedClient()->request('PUT', UriTemplate::resolve('{realm}/client-scopes/{scopeId}/protocol-mappers/models/{mapperId}', [
            'realm' => $this->keycloakRealm,
            'scopeId' => $scopeId,
            'mapperId' => $mapperId,
        ]), [
            'json' => array_merge(['id' => $mapperId], $data),
        ]);
    }

    public function addClientScopeProtocolMapper(string $scopeId, array $data): void
    {
        $this->getAuthenticatedClient()->request('POST', UriTemplate::resolve('{realm}/client-scopes/{scopeId}/protocol-mappers/models', [
            'realm' => $this->keycloakRealm,
            'scopeId' => $scopeId,
        ]), [
            'json' => $data,
        ]);
    }

    public function createScope(string $name, array $data = []): void
    {
        $scope = $this->getScopeByName($name);
        $data = array_merge([
            'name' => $name,
            'protocol' => 'openid-connect',
            'type' => 'none',
            'attributes' => [
                'include.in.token.scope' => 'true',
                'display.on.consent.screen' => 'false',
            ],
        ], $data);

        if (!$scope) {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/client-scopes', [
                    'realm' => $this->keycloakRealm,
                ]), [
                    'json' => $data,
                ]);
            $scope = $this->getScopeByName($name);
        } else {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/client-scopes/{id}', [
                    'realm' => $this->keycloakRealm,
                    'id' => $scope['id'],
                ]), [
                    'json' => $data,
                ]);
        }

        $isDefault = 'default' === $data['type'];

        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('DELETE', UriTemplate::resolve('{realm}/default-default-client-scopes/{id}', [
                'realm' => $this->keycloakRealm,
                'id' => $scope['id'],
            ])), 404, []);

        if ($isDefault) {
            HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/default-default-client-scopes/{id}', [
                    'realm' => $this->keycloakRealm,
                    'id' => $scope['id'],
                ])), 409, []);
        }
    }

    protected function getScopes(): array
    {
        $response = $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/client-scopes', [
            'realm' => $this->keycloakRealm,
        ]));

        return $response->toArray();
    }

    private function getScopeByName(string $name): ?array
    {
        $scopes = $this->getScopes();

        foreach ($scopes as $scope) {
            if ($name === $scope['name']) {
                return $scope;
            }
        }

        return null;
    }

    public function addScopeToClient(string $scope, string $clientId): void
    {
        $scopeData = $this->getScopeByName($scope);

        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/default-client-scopes/{scopeId}', [
                'realm' => $this->keycloakRealm,
                'clientId' => $clientId,
                'scopeId' => $scopeData['id'],
            ])), 409, []);
    }

    public function addServiceAccountRole(
        array $client,
        string $role,
        string $fromClientId,
    ): void {
        $data = [
            'role' => $role,
        ];

        $serviceAccountUser = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{clientId}/service-account-user', [
                'realm' => $this->keycloakRealm,
                'clientId' => $client['id'],
            ]), [
                'json' => $data,
            ])->toArray();

        $realmClient = $this->getClientByClientId($fromClientId);
        if (null === $realmClient) {
            throw new \InvalidArgumentException(sprintf('Client "%s" not found in realm "%s"', $fromClientId, $this->keycloakRealm));
        }

        $roleToGrant = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{realmClientId}/roles/{roleName}', [
                'realm' => $this->keycloakRealm,
                'realmClientId' => $realmClient['id'],
                'roleName' => $role,
            ]), [
                'json' => $data,
            ])->toArray();

        $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users/{userId}/role-mappings/clients/{clientId}', [
                'realm' => $this->keycloakRealm,
                'userId' => $serviceAccountUser['id'],
                'clientId' => $realmClient['id'],
            ]), [
                'json' => [[
                    'id' => $roleToGrant['id'],
                    'name' => $roleToGrant['name'],
                ]],
            ]);
    }

    public function createClient(
        string $clientId,
        ?string $clientSecret,
        ?string $baseUri,
        array $data = [],
    ): array {
        $client = $this->getClientByClientId($clientId);

        $data = array_merge([
            'clientId' => $clientId,
            'secret' => $clientSecret,
            'publicClient' => null === $clientSecret,
            'frontchannelLogout' => false,
            'rootUrl' => $baseUri,
            'redirectUris' => $baseUri ? [
                $baseUri.'/*',
            ] : null,
        ], $data);

        if (null !== $client) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{id}', [
                    'realm' => $this->keycloakRealm,
                    'id' => $client['id'],
                ]), [
                    'json' => $data,
                ]);
        } else {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/clients', [
                    'realm' => $this->keycloakRealm,
                ]), [
                    'json' => $data,
                ]);
            $client = $this->getClientByClientId($clientId);
        }

        return $client;
    }

    public function configureClientClaim(
        array $client,
        array $data,
    ): void {
        $protocolMappers = $client['protocolMappers'] ?? [];
        $protocolMapper = array_values(array_filter($protocolMappers, fn (array $pm): bool => $pm['name'] === $data['name']))[0] ?? null;

        if ($protocolMapper) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/protocol-mappers/models/{id}', [
                    'realm' => $this->keycloakRealm,
                    'clientId' => $client['id'],
                    'id' => $protocolMapper['id'],
                ]), [
                    'json' => $data + ['id' => $protocolMapper['id']],
                ]);
        } else {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/clients/{clientId}/protocol-mappers/models', [
                    'realm' => $this->keycloakRealm,
                    'clientId' => $client['id'],
                ]), [
                    'json' => $data,
                ]);
        }
    }

    public function createUser(array $data): array
    {
        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users', [
                'realm' => $this->keycloakRealm,
            ]), [
                'json' => $data,
            ]), 409, $data);

        $user = $this->getUsers([
            'query' => [
                'username' => $data['username'],
            ],
        ])[0];

        if ($user['username'] !== $data['username']) {
            throw new \InvalidArgumentException(sprintf('Invalid user "%s" "%s"', $user['username'], $data['username']));
        }

        return $user;
    }

    public function getUsers(array $options = []): array
    {
        return $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/users', [
                'realm' => $this->keycloakRealm,
            ]), $options)->toArray();
    }

    public function createGroup(array $data): array
    {
        $groupName = $data['name'];
        $existingGroup = $this->getGroupByName($groupName);

        if ($existingGroup) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/groups/{groupId}', [
                    'realm' => $this->keycloakRealm,
                    'groupId' => $existingGroup['id'],
                ]), [
                    'json' => $data,
                ]);
        } else {
            $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/groups', [
                'realm' => $this->keycloakRealm,
            ]), [
                'json' => $data,
            ]);
        }

        $group = $this->getGroupByName($groupName);
        if ($group['name'] !== $groupName) {
            throw new \InvalidArgumentException(sprintf('Invalid group "%s" "%s"', $group['name'], $groupName));
        }

        return $group;
    }

    public function getGroupByName(string $name): ?array
    {
        return $this->getGroups([
            'query' => [
                'exact' => 'true',
                'search' => $name,
            ],
        ])[0] ?? null;
    }

    public function getGroups(array $options = []): array
    {
        return $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/groups', [
                'realm' => $this->keycloakRealm,
            ]), $options)->toArray();
    }

    public function createRole(string $name, string $description): void
    {
        $data = [
            'name' => $name,
            'clientRole' => false,
            'description' => $description,
        ];

        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/roles', [
                'realm' => $this->keycloakRealm,
            ]), [
                'json' => $data,
            ]), 409, $data);
    }

    public function getRealmRoles(): array
    {
        $response = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/roles', [
                'realm' => $this->keycloakRealm,
            ]));

        return $response->toArray();
    }

    public function getRealmClientRoles(): array
    {
        $realmClient = $this->getClientByClientId('realm-management');

        $response = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{realmClientId}/roles', [
                'realm' => $this->keycloakRealm,
                'realmClientId' => $realmClient['id'],
            ]));

        return $response->toArray();
    }

    public function getUserRoles(string $userId): array
    {
        return $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/users/{userId}/role-mappings/realm', [
                'realm' => $this->keycloakRealm,
                'userId' => $userId,
            ]))->toArray();
    }

    public function addRolesToUser(string $userId, array $roleNames): void
    {
        $allRoles = $this->getRealmRoles();
        $userRoles = array_map(fn (array $r): string => $r['id'], $this->getUserRoles($userId));

        $roles = array_map(function (string $roleName) use ($userRoles, $allRoles): ?array {
            foreach ($allRoles as $r) {
                if ($roleName === $r['name']) {
                    if (!in_array($r['id'], $userRoles, true)) {
                        return $r;
                    } else {
                        return null;
                    }
                }
            }

            throw new \InvalidArgumentException(sprintf('Role "%s" not found', $roleName));
        }, $roleNames);
        $roles = array_filter($roles, fn (?array $r): bool => null !== $r);

        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users/{userId}/role-mappings/realm', [
                'realm' => $this->keycloakRealm,
                'userId' => $userId,
            ]), [
                'json' => $roles,
            ]), 409, $roles);
    }

    public function addClientRolesToUser(string $userId, array $roleNames): void
    {
        $realmClient = $this->getClientByClientId('realm-management');
        $allRoles = $this->getRealmClientRoles();
        $userRoles = array_map(fn (array $r): string => $r['id'], $this->getUserRoles($userId));

        $roles = array_map(function (string $roleName) use ($userRoles, $allRoles): ?array {
            foreach ($allRoles as $r) {
                if ($roleName === $r['name']) {
                    if (!in_array($r['id'], $userRoles, true)) {
                        return $r;
                    } else {
                        return null;
                    }
                }
            }

            throw new \InvalidArgumentException(sprintf('Role "%s" not found', $roleName));
        }, $roleNames);
        $roles = array_filter($roles, fn (?array $r): bool => null !== $r);

        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users/{userId}/role-mappings/clients/{realmClientId}', [
                'realm' => $this->keycloakRealm,
                'userId' => $userId,
                'realmClientId' => $realmClient['id'],
            ]), [
                'json' => $roles,
            ]), 409, $roles);
    }

    public function addUserToGroup(string $userId, string $groupId): void
    {
        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/users/{userId}/groups/{groupId}', [
                'realm' => $this->keycloakRealm,
                'userId' => $userId,
                'groupId' => $groupId,
            ])), 409, []);
    }

    public function putRealm(array $data): ResponseInterface
    {
        return $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}', [
                'realm' => $this->keycloakRealm,
            ]), [
                'json' => $data,
            ]);
    }

    public function createIdentityProvider(array $data): array
    {
        $idpAlias = $data['alias'];
        $existingIdp = $this->getIdentityProviderByAlias($idpAlias);

        if ($existingIdp) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/identity-provider/instances/{alias}', [
                    'realm' => $this->keycloakRealm,
                    'alias' => $existingIdp['alias'],
                ]), [
                    'json' => $data,
                ]);
        } else {
            HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/identity-provider/instances', [
                    'realm' => $this->keycloakRealm,
                ]), [
                    'json' => $data,
                ]), null, $data);
        }

        $idp = $this->getIdentityProviderByAlias($idpAlias);
        if ($idp['alias'] !== $idpAlias) {
            throw new \InvalidArgumentException(sprintf('Invalid IdP "%s" "%s"', $idp['alias'], $idpAlias));
        }

        return $idp;
    }

    public function getIdentityProviderByAlias(string $alias): ?array
    {
        try {
            return $this->getAuthenticatedClient()
                ->request('GET', UriTemplate::resolve('{realm}/identity-provider/instances/{alias}', [
                    'realm' => $this->keycloakRealm,
                    'alias' => $alias,
                ]))->toArray();
        } catch (ClientException $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }

        return null;
    }

    public function createIdpMapper(string $idpAlias, array $data): void
    {
        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/identity-provider/instances/{alias}/mappers', [
                'realm' => $this->keycloakRealm,
                'alias' => $idpAlias,
            ]), [
                'json' => $data,
            ]), 400, $data);
    }

    public function linkAccountToIdentityProvider(string $userId, string $idpAlias, array $data): void
    {
        HttpClientUtil::debugError(fn () => $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users/{userId}/federated-identity/{alias}', [
                'realm' => $this->keycloakRealm,
                'userId' => $userId,
                'alias' => $idpAlias,
            ]), [
                'json' => $data,
            ]), 409, $data);
    }
}
