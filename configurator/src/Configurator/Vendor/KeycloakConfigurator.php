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
        private readonly array $frontendApplications,
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
        $this->configureScope('openid');
        $this->configureScope('groups');

        foreach ($this->symfonyApplications as $app) {
            $client = $this->createClient(
                getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app))),
                getenv(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                getenv(sprintf('%s_API_URL', strtoupper($app))).'/admin',
                [
                    'serviceAccountsEnabled' => true,
                ]
            );

            $this->addServiceAccountRole($client, 'view-users', $this->realm.'-realm');
            $this->addServiceAccountRole($client, 'view-groups', 'account');
        }

        foreach ($this->frontendApplications as $app) {
            $this->createClient(
                getenv(sprintf('%s_CLIENT_ID', strtoupper($app))),
                null,
                getenv(sprintf('%s_CLIENT_URL', strtoupper($app))),
                [
                    'serviceAccountsEnabled' => false,
                ]
            );
        }
    }

    private function configureScope(string $name): void
    {
        $scopes = $this->getScopes();
        $scope = $this->getScopeByName($scopes, $name);
        $data = [
            'name' => $name,
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
            $scope = $this->getScopeByName($scopes, $name);
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
        ?string $clientSecret,
        string $baseUri,
        array $data = [],
    ): array {
        $clients = $this->getClients();
        $client = $this->getClientByClientId($clients, $clientId);

        $data = array_merge([
            'clientId' => $clientId,
            'secret' => $clientSecret,
            'publicClient' => null === $clientSecret,
            'frontchannelLogout' => false,
            'rootUrl' => $baseUri,
            'redirectUris' => [
                $baseUri.'/*',
            ],
        ], $data);

        if (null !== $client) {
            $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{id}', [
                    'realm' => $this->realm,
                    'id' => $client['id'],
                ]), [
                    'json' => $data,
                ]);
        } else {
            $this->getAuthenticatedClient()
                ->request('POST', UriTemplate::resolve('{realm}/clients', [
                    'realm' => $this->realm,
                ]), [
                    'json' => $data,
                ]);
            $clients = $this->getClients();
            $client = $this->getClientByClientId($clients, $clientId);
        }

        $scopes = $this->getScopes();

        foreach ([
            'openid',
            'profile',
                 ] as $scope) {
            $scopeData = $this->getScopeByName($scopes, $scope);

            HttpClientUtil::catchHttpCode(fn () => $this->getAuthenticatedClient()
                ->request('PUT', UriTemplate::resolve('{realm}/clients/{clientId}/default-client-scopes/{scopeId}', [
                    'realm' => $this->realm,
                    'clientId' => $client['id'],
                    'scopeId' => $scopeData['id'],
                ]), [
                    'json' => $data,
                ]), 409);
        }

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
                'id.token.claim' => 'true',
                'multivalued' => 'true',
                'usermodel.realmRoleMapping.rolePrefix' => '',
            ],
        ]);

        $this->configureClientClaim($client, [
            'name' => 'groups',
            'consentRequired' => false,
            'protocol' => 'openid-connect',
            'protocolMapper' => 'oidc-group-membership-mapper',
            'config' => [
                'claim.name' => 'groups',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
                'id.token.claim' => 'true',
                'full.path' => 'false',
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

    private function addServiceAccountRole(
        array $client,
        string $role,
        string $fromClientId,
    ): void {
        $data = [
            'role' => $role,
        ];

        $serviceAccountUser =  $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{clientId}/service-account-user', [
                'realm' => $this->realm,
                'clientId' => $client['id'],
            ]), [
                'json' => $data,
            ])->toArray();


        $clients = $this->getClients();
        $realmClient = $this->getClientByClientId($clients, $fromClientId);

        $roleToGrant = $this->getAuthenticatedClient()
            ->request('GET', UriTemplate::resolve('{realm}/clients/{realmClientId}/roles/{roleName}', [
                'realm' => $this->realm,
                'realmClientId' => $realmClient['id'],
                'roleName' => $role,
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
                    'id' => $roleToGrant['id'],
                    'name' => $roleToGrant['name'],
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
