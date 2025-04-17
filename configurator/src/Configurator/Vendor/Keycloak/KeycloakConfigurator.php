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
        $hasDevPreset = in_array('dev', $presets, true);

        $this->configureRealm();
        $this->configureDefaultClientScopes();

        $adminSubRoles = [
            KeycloakInterface::ROLE_USER_ADMIN => [
                'description' => 'Manage Users',
                'roles' => [],
            ],
            KeycloakInterface::ROLE_GROUP_ADMIN => [
                'description' => 'Manage Groups',
                'roles' => [],
            ],
        ];
        foreach ($this->symfonyApplications as $app) {
            $adminSubRoles[$app.'-admin'] = [
                'description' => sprintf('Admin access for %s', ucwords($app)),
            ];
        }

        $roleHierarchy = [
            KeycloakInterface::ROLE_ADMIN => [
                'description' => 'Can do anything',
                'roles' => $adminSubRoles,
            ],
            KeycloakInterface::ROLE_TECH => [
                'description' => 'Access to Dev/Ops Operations',
                'roles' => [],
            ],
        ];
        $this->keycloakManager->createRoleHierarchy($roleHierarchy);

        foreach ([
            'openid',
            'groups',
        ] as $scope) {
            $this->keycloakManager->createScope($scope);
        }

        foreach ($this->getAppScopes() as $app => $appScopes) {
            foreach ($appScopes as $scope) {
                $this->keycloakManager->createScope($scope, [
                    'description' => sprintf('%s in %s', $scope, ucwords($app)),
                ]);
            }
        }

        $appScopes = $this->getAppScopes();
        foreach ($this->symfonyApplications as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $baseUri = getenv(sprintf('%s_API_URL', strtoupper($app)));

            $clientData = $this->configureClient(
                $clientId,
                getenv(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                $baseUri,
                [
                    'serviceAccountsEnabled' => true,
                ],
                redirectUris: [
                    $baseUri.'/admin/*',
                    $baseUri.'/bundles/apiplatform/swagger-ui/oauth2-redirect.html',
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

        $defaultAdminUsername = getenv('DEFAULT_ADMIN_USERNAME');
        $defaultAdminEmail = $defaultAdminUsername;
        if (!str_contains($defaultAdminEmail, '@')) {
            $defaultAdminEmail .= '@'.(getenv('PHRASEA_DOMAIN') ?: 'phrasea.io');
        }

        $defaultAdmin = $this->keycloakManager->createUser([
            'username' => $defaultAdminUsername,
            'email' => $defaultAdminEmail,
            'enabled' => true,
            'firstName' => 'Admin',
            'lastName' => 'Admin',
            'credentials' => [[
                'type' => 'password',
                'value' => getenv('DEFAULT_ADMIN_PASSWORD'),
                'temporary' => !$hasTestPreset,
            ]],
        ]);

        $this->keycloakManager->addRolesToUser($defaultAdmin['id'], [
            KeycloakInterface::ROLE_ADMIN,
        ]);
        $this->keycloakManager->addClientRolesToUser($defaultAdmin['id'], [
            'realm-admin',
        ]);

        if ($hasDevPreset) {
            $this->keycloakManager->createClient('postman', null, null, [
                'standardFlowEnabled' => false,
                'implicitFlowEnabled' => false,
                'directAccessGrantsEnabled' => true,
                'serviceAccountsEnabled' => false,
            ]);
        }
    }

    private function getAppScopes(): array
    {
        return [
            'databox' => array_merge([
                'super-admin',
            ], ...array_map(fn (string $ns): array => array_map(fn (string $p): string => $ns.':'.$p, [
                'create',
                'list',
                'read',
                'edit',
                'delete',
                'operator',
                'owner',
            ]), [
                'asset',
                'collection',
                'rendition',
                'rendition-class',
                'rendition-rule',
                'workspace',
            ])),
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
        ?array $redirectUris = null,
    ): array {
        $clientData = $this->keycloakManager->createClient(
            $clientId,
            $clientSecret,
            $baseUri,
            $data,
            $redirectUris,
        );

        foreach ([
            'openid',
            'profile',
        ] as $scope) {
            $this->keycloakManager->addScopeToClient($scope, $clientData['id']);
        }

        return $clientData;
    }

    private function configureRealm(): void
    {
        $from = getenv('MAIL_FROM') ?: 'noreply@phrasea.io';

        $this->keycloakManager->createRealm();

        $this->keycloakManager->putRealm([
            'displayName'               => 'Phrasea Auth',
            'displayNameHtml'           => '<div class="kc-logo-text"><span>Phrasea Auth</span></div>',
            'registrationAllowed'       => $this->getBooleanEnv('KEYCLOAK_LOGIN_REGISTRATION_ALLOWED', false),
            'resetPasswordAllowed'      => $this->getBooleanEnv('KEYCLOAK_LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe'                => $this->getBooleanEnv('KEYCLOAK_LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed'     => $this->getBooleanEnv('KEYCLOAK_LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail'               => $this->getBooleanEnv('KEYCLOAK_LOGIN_VERIFY_EMAIL_ALLOWED', false),
            'registrationEmailAsUsername' => $this->getBooleanEnv('KEYCLOAK_LOGIN_EMAIL_AS_USERNAME', false),
            'editUsernameAllowed'       => $this->getBooleanEnv('KEYCLOAK_LOGIN_EDIT_USERNAME', false),
            'bruteForceProtected'       => $this->getBooleanEnv('KEYCLOAK_SECURITY_DETECTION_BRUTE_FORCE_ENABLED', false),
            'ssoSessionIdleTimeout'     => getenv('KEYCLOAK_SSO_SESSION_IDLE_TIMEOUT') ?: '1800',
            'clientSessionIdleTimeout'  => getenv('KEYCLOAK_CLIENT_SESSION_IDLE_TIMEOUT') ?: '1800',
            'offlineSessionIdleTimeout' => getenv('KEYCLOAK_OFFLINE_SESSION_IDLE_TIMEOUT') ?: '2592000',
            'internationalizationEnabled' => $this->getBooleanEnv('KEYCLOAK_LOCALISATION_ENABLED', true),
            'supportedLocales'          => (getenv('KEYCLOAK_SUPPORTED_LOCALES') != null) ? explode(',', getenv('KEYCLOAK_SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale'             => getenv('KEYCLOAK_DEFAULT_LOCALE') ?: 'en',
            'smtpServer' => [
                'auth' => getenv('EMAIL_USER') ? 'true' : '',
                'from' => $from,
                'fromDisplayName' => 'Phrasea',
                'host' => getenv('EMAIL_HOST_RELAY'),
                'port' => getenv('EMAIL_HOST_PORT') ?? '587',
                'replyTo' => '',
                'ssl' => 'false',
                'starttls' => 'false',
                'user' => getenv('EMAIL_USER') ?? null,
                'password' => getenv('EMAIL_SECRET') ?? null,
            ],
        ]);
    }

    private function getBooleanEnv(string $name, bool $defaultValue= false): bool
    {
        switch (getenv($name)) {
            case 'true':
                return true;
                break;
            case 'false':
                return false;
                break;
            default:
                return $defaultValue;
                break;    
        }
    }

    private function configureDefaultClientScopes(): void
    {
        $rolesScope = $this->keycloakManager->getDefaultClientScopesByName('roles');
        if (null === $rolesScope) {
            throw new \InvalidArgumentException(sprintf('Scope named "roles" not found in client scopes'));
        }
        $scopeId = $rolesScope['id'];

        $protocolMapper = $this->keycloakManager->getClientScopeProtocolMapperByName($scopeId, 'realm roles');

        $rolesMapperData = [
            'protocol' => 'openid-connect',
            'protocolMapper' => 'oidc-usermodel-realm-role-mapper',
            'name' => 'realm roles',
            'config' => [
                'usermodel.realmRoleMapping.rolePrefix' => '',
                'multivalued' => 'true',
                'claim.name' => 'roles',
                'jsonType.label' => 'String',
                'id.token.claim' => 'false',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
                'user.attribute' => 'foo',
            ],
            'consentRequired' => false,
        ];
        if (null === $protocolMapper) {
            $this->keycloakManager->addClientScopeProtocolMapper($scopeId, $rolesMapperData);
        } else {
            $this->keycloakManager->putClientScopeProtocolMapper($scopeId, $protocolMapper['id'], $rolesMapperData);
        }

        $this->keycloakManager->putClientScopeProtocolMapper($scopeId, $protocolMapper['id'], $rolesMapperData);

        $protocolMapper = $this->keycloakManager->getClientScopeProtocolMapperByName($scopeId, 'groups');
        $groupMapperData = [
            'protocol' => 'openid-connect',
            'protocolMapper' => 'oidc-group-uuid-mapper',
            'name' => 'groups',
            'config' => [
                'claim.name' => 'groups',
                'id.token.claim' => 'true',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
            ],
        ];
        if (null === $protocolMapper) {
            $this->keycloakManager->addClientScopeProtocolMapper($scopeId, $groupMapperData);
        } else {
            $this->keycloakManager->putClientScopeProtocolMapper($scopeId, $protocolMapper['id'], $groupMapperData);
        }
    }
}
