<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

use App\Configurator\ConfiguratorInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class KeycloakConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private KeycloakManager $keycloakManager,
        private array $symfonyApplications,
        private array $frontendApplications,
    ) {
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $hasTestPreset = in_array('test', $presets, true);

        $this->configureRealm();

        foreach ($this->symfonyApplications as $app) {
            $this->keycloakManager->createRole($app.'-admin', sprintf('Admin access for %s', ucwords($app)));
        }

        foreach ([
                     KeycloakInterface::ROLE_ADMIN => 'Can do anything',
                     KeycloakInterface::ROLE_TECH => 'Access to Dev/Ops Operations',
                     KeycloakInterface::ROLE_USER_ADMIN => 'Manage Users',
                     KeycloakInterface::ROLE_GROUP_ADMIN => 'Manage Groups',
                 ] as $role => $desc) {
            $this->keycloakManager->createRole($role, $desc);
        }

        foreach ([
                     'openid',
                     'groups',
                 ] as $scope) {
            $this->keycloakManager->createScope($scope);
        }

        foreach ($this->getAppScopes() as $app => $appScopes) {
            foreach ($appScopes as $scope) {
                $this->keycloakManager->createScope($scope, [
                    'description' => sprintf('%s in %s', $scope, ucwords($app))
                ]);
            }
        }

        $appScopes = $this->getAppScopes();
        foreach ($this->symfonyApplications as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $clientData = $this->configureClient(
                $clientId,
                getenv(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                getenv(sprintf('%s_API_URL', strtoupper($app))).'/admin',
                [
                    'serviceAccountsEnabled' => true,
                ]
            );

            $this->keycloakManager->addServiceAccountRole($clientData, 'view-users', 'realm-management');

            foreach ($this->getAdminClientServiceAccountRoles()[$app] ?? [] as $role) {
                $this->keycloakManager->addServiceAccountRole($clientData, $role, 'realm-management');
            }

            if (isset($appScopes[$app])) {
                foreach ($appScopes[$app] as $scope) {
                    $this->keycloakManager->addScopeToClient($scope, $clientData['id']);
                }
            }
        }

        foreach ($this->frontendApplications as $app) {
            $this->configureClient(
                getenv(sprintf('%s_CLIENT_ID', strtoupper($app))),
                null,
                getenv(sprintf('%s_CLIENT_URL', strtoupper($app))),
                [
                    'serviceAccountsEnabled' => false,
                ]
            );
        }

        if (getenv('INDEXER_DATABOX_CLIENT_ID')) {
            $clientData = $this->keycloakManager->createClient(
                getenv('INDEXER_DATABOX_CLIENT_ID'),
                getenv('INDEXER_DATABOX_CLIENT_SECRET'),
                null,
                [
                    'standardFlowEnabled' => false,
                    'implicitFlowEnabled' => false,
                    'directAccessGrantsEnabled' => false,
                    'serviceAccountsEnabled' => true,
                ],
            );

            foreach ($this->getAppScopes()['databox'] as $scope) {
                $this->keycloakManager->addScopeToClient($scope, $clientData['id']);
            }
        }

        $defaultAdmin = $this->keycloakManager->createUser([
            'username' => getenv('DEFAULT_ADMIN_USERNAME'),
            'enabled' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => getenv('DEFAULT_ADMIN_PASSWORD'),
                'temporary' => !$hasTestPreset,
            ]]
        ]);

        $this->keycloakManager->addRolesToUser($defaultAdmin['id'], [
            KeycloakInterface::ROLE_ADMIN,
        ]);
        $this->keycloakManager->addClientRolesToUser($defaultAdmin['id'], [
            'realm-admin',
        ]);
    }

    private function getAppScopes(): array
    {
        return [
            'databox' => [
                'super-admin',
                'asset:create',
                'asset:delete',
                'asset:edit',
                'collection:create',
                'collection:delete',
                'collection:edit',
                'rendition:create',
                'rendition:delete',
                'rendition:edit',
                'workspace:create',
                'workspace:delete',
                'workspace:edit',
            ],
            'expose' => [
                'publish',
            ],
            'uploader' => [
                'commit:list',
            ],
        ];
    }

    private function getAdminClientServiceAccountRoles(): array
    {
        return [
            'databox' => [
                'manage-users',
            ],
        ];
    }

    private function configureClient(
        string $clientId,
        ?string $clientSecret,
        string $baseUri,
        array $data = [],
    ): array {
        $clientData = $this->keycloakManager->createClient(
            $clientId,
            $clientSecret,
            $baseUri,
            $data,
        );

        foreach ([
            'openid',
            'profile',
                 ] as $scope) {
            $this->keycloakManager->addScopeToClient($scope, $clientData['id']);
        }

        $this->keycloakManager->configureClientClaim($clientData, [
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

        $this->keycloakManager->configureClientClaim($clientData, [
            'name' => 'groups',
            'consentRequired' => false,
            'protocol' => 'openid-connect',
            'protocolMapper' => 'oidc-group-uuid-mapper',
            'config' => [
                'claim.name' => 'groups',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
                'id.token.claim' => 'true',
            ],
        ]);

        return $clientData;
    }

    private function configureRealm(): void
    {
        $from = getenv('MAIL_FROM') ?: 'noreply@phrasea.io';
        $mailer = parse_url(getenv('MAILER_DSN'));

        $this->keycloakManager->createRealm();

        $this->keycloakManager->putRealm([
            'displayName' => 'Phrasea Auth',
            'displayNameHtml' => '<div class="kc-logo-text"><span>Phrasea Auth</span></div>',
            'resetPasswordAllowed' => true,
            'rememberMe' => true,
            'smtpServer' => [
                'auth' => isset($mailer['user']) ? 'true' : '',
                'from' => $from,
                'fromDisplayName' => 'Phrasea',
                'host' => $mailer['host'],
                'port' => $mailer['port'] ?? '587',
                'replyTo' => '',
                'ssl' => 'false',
                'starttls' => 'false',
                'user' => $mailer['user'] ?? null,
                'password' => $mailer['pass'] ?? null,
            ],
        ]);
    }
}
