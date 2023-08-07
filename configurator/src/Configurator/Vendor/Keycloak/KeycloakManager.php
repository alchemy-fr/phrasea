<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

use App\Util\HttpClientUtil;
use App\Util\UriTemplate;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakManager
{
    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $keycloakClient,
        private readonly string $keycloakRealm,
    )
    {
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
            'realm' => $this->keycloakRealm,
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

    protected function getClients(): array
    {
        return $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/clients', [
            'realm' => $this->keycloakRealm,
        ]))->toArray();
    }

    public function getClientByClientId(string $clientId): ?array
    {
        $clients = $this->getClients();
        foreach ($clients as $client) {
            if ($clientId === $client['clientId']) {
                return $client;
            }
        }

        return null;
    }

    public function createScope(string $name, array $data = []): void
    {
        $scope = $this->getScopeByName($name);
        $data = array_merge([
            'name' => $name,
            'protocol' => 'openid-connect',
            'type' => 'default',
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

        HttpClientUtil::catchHttpCode(fn() => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/default-default-client-scopes/{id}', [
                'realm' => $this->keycloakRealm,
                'id' => $scope['id'],
            ])), 409);
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

        HttpClientUtil::catchHttpCode(fn () => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/default-client-scopes/{scopeId}', [
                'realm' => $this->keycloakRealm,
                'clientId' => $clientId,
                'scopeId' => $scopeData['id'],
            ])), 409);
    }

    public function addServiceAccountRole(
        array $client,
        string $role,
        string $fromClientId,
    ): void {
        $data = [
            'role' => $role,
        ];

        $serviceAccountUser =  $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{clientId}/service-account-user', [
                'realm' => $this->keycloakRealm,
                'clientId' => $client['id'],
            ]), [
                'json' => $data,
            ])->toArray();


        $realmClient = $this->getClientByClientId($fromClientId);

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
        $protocolMappers = $client['protocolMappers'];
        $protocolMapper = array_values(array_filter($protocolMappers, fn(array $pm): bool => $pm['name'] === $data['name']))[0] ?? null;

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
}
