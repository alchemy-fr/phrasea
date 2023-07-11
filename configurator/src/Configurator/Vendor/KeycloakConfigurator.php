<?php

declare(strict_types=1);

namespace App\Configurator\Vendor;

use App\Configurator\ConfiguratorInterface;
use App\Util\HttpClientUtil;
use App\Util\UriTemplate;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakConfigurator implements ConfiguratorInterface
{
    private ?string $token = null;
    private readonly HttpClientInterface $client;

    public function __construct(
        private readonly array $symfonyApplications,
        HttpClientInterface $client,
        private readonly string $realm = 'master',
    ) {
        $this->client = $client->withOptions([
            'base_uri' => getenv('KEYCLOAK_URL').'/admin/realms/',
            'verify_peer' => false,
        ]);
    }

    private function getAuthenticatedClient(): HttpClientInterface
    {
        return $this->client
            ->withOptions([
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getToken(),
                ],
            ]);
    }

    public function configure(OutputInterface $output): void
    {
        $this->configureScopes();

        foreach ($this->symfonyApplications as $app) {
            $client = $this->createClient(
                getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app))),
                getenv(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                getenv(sprintf('%s_API_BASE_URL', strtoupper($app))).'/admin',
            );

            $this->configureServiceAccountRoles($client, [
                'role' => 'view-users',
            ]);
        }
    }

    private function configureScopes(): void
    {
        $scopes = $this->getScopes();
        $scope = $this->getScopeByName($scopes, 'openid');
        $data = [
            'name' => 'openid',
            'protocol' => 'openid-connect',
            'type' => 'default',
            'attributes' => [
                'include.in.token.scope' => 'true',
                'display.on.consent.screen' => 'false',
            ],
        ];

        if (!$scope) {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/client-scopes', [
                    'realm' => $this->realm,
                ]), [
                    'json' => $data,
                ]);
            $scopes = $this->getScopes();
            $scope = $this->getScopeByName($scopes, 'openid');
        } else {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/client-scopes/{id}', [
                    'realm' => $this->realm,
                    'id' => $scope['id'],
                ]), [
                    'json' => $data,
                ]);
        }


        HttpClientUtil::catchHttpCode(fn() => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/default-default-client-scopes/{id}', [
                'realm' => $this->realm,
                'id' => $scope['id'],
            ])), 409);
    }

    private function createClient(
        string $clientId,
        string $clientSecret,
        string $baseUri,
    ): array {
        $clients = $this->getClients();
        $client = $this->getClientByClientId($clients, $clientId);

        $data = [
            'clientId' => $clientId,
            'secret' => $clientSecret,
            'frontchannelLogout' => false,
            'serviceAccountsEnabled' => true,
            'rootUrl' => $baseUri,
            'redirectUris' => [
                $baseUri.'/*',
            ],
            'defaultClientScopes' => ['openid'],
        ];

        if (null !== $client) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{id}', [
                    'realm' => $this->realm,
                    'id' => $client['id'],
                ]), [
                    'json' => $data,
                ]);
        } else {
            $client = $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/clients', [
                    'realm' => $this->realm,
                ]), [
                    'json' => $data,
                ]);
        }

        $scopes = $this->getScopes();
        $openidScope = $this->getScopeByName($scopes, 'openid');

        HttpClientUtil::catchHttpCode(fn () => $this->getAuthenticatedClient()
            ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/default-client-scopes/{scopeId}', [
                'realm' => $this->realm,
                'clientId' => $client['id'],
                'scopeId' => $openidScope['id'],
            ]), [
                'json' => $data,
            ]), 409);

        $this->configureClientClaim($client, [
            'name' => 'roles',
            'consentRequired' => false,
            'protocol' => 'openid-connect',
            'protocolMapper' => 'oidc-usermodel-realm-role-mapper',
            'config' => [
                'claim.name' => 'roles',
                'jsonType.label' => 'String',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
                'id.token.claim' => 'false',
                'multivalued' => 'true',
                'usermodel.realmRoleMapping.rolePrefix' => '',
            ],
        ]);

        return $client;
    }

    private function configureClientClaim(
        array $client,
        array $data,
    ): void {
        $protocolMappers = $client['protocolMappers'];
        $protocolMapper = array_values(array_filter($protocolMappers, fn(array $pm): bool => $pm['name'] === $data['name']))[0] ?? null;

        if ($protocolMapper) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/protocol-mappers/models/{id}', [
                    'realm' => $this->realm,
                    'clientId' => $client['id'],
                    'id' => $protocolMapper['id'],
                ]), [
                    'json' => $data + ['id' => $protocolMapper['id']],
                ]);
        } else {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/clients/{clientId}/protocol-mappers/models', [
                    'realm' => $this->realm,
                    'clientId' => $client['id'],
                ]), [
                    'json' => $data,
                ]);
        }
    }

    private function configureServiceAccountRoles(
        array $client,
        array $data,
    ): void {
        $serviceAccountUser =  $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{clientId}/service-account-user', [
                'realm' => $this->realm,
                'clientId' => $client['id'],
            ]), [
                'json' => $data,
            ])->toArray();


        $clients = $this->getClients();
        $realmClient = $this->getClientByClientId($clients, $this->realm.'-realm');

        $viewUsersRole = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{realmClientId}/roles/view-users', [
                'realm' => $this->realm,
                'realmClientId' => $realmClient['id'],
            ]), [
                'json' => $data,
            ])->toArray();

        $this->getAuthenticatedClient()
            ->request('POST', UriTemplate::resolve('{realm}/users/{userId}/role-mappings/clients/{clientId}', [
                'realm' => $this->realm,
                'userId' => $serviceAccountUser['id'],
                'clientId' => $realmClient['id'],
            ]), [
                'json' => [[
                    'id' => $viewUsersRole['id'],
                    'name' => $viewUsersRole['name'],
                ]],
            ]);
    }

    protected function getScopes(): array
    {
        $response = $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/client-scopes', [
            'realm' => $this->realm,
        ]));

        return $response->toArray();
    }

    private function getScopeByName(array $scopes, string $name): ?array
    {
        foreach ($scopes as $scope) {
            if ($name === $scope['name']) {
                return $scope;
            }
        }

        return null;
    }

    protected function getClients(): array
    {
        return $this->getAuthenticatedClient()->request('GET', UriTemplate::resolve('{realm}/clients', [
            'realm' => $this->realm,
        ]))->toArray();
    }

    private function getClientByClientId(array $clients, string $clientId): ?array
    {
        foreach ($clients as $client) {
            if ($clientId === $client['clientId']) {
                return $client;
            }
        }

        return null;
    }

    private function getToken(): string
    {
        if (null !== $this->token) {
            return $this->token;
        }

        $response = $this->client->request('POST', UriTemplate::resolve('/realms/{realm}/protocol/openid-connect/token', [
            'realm' => $this->realm,
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
}
