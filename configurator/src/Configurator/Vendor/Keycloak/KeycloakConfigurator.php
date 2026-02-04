<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

use App\Configurator\ConfiguratorInterface;
use App\Service\ServiceWaiter;
use App\Util\EnvHelper;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class KeycloakConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private KeycloakManager $keycloakManager,
        private array $symfonyApplications,
        private array $frontendApplications,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public static function getName(): string
    {
        return 'keycloak';
    }

    public static function getPriority(): int
    {
        return -100;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $keycloakUrl = EnvHelper::getEnvOrThrow('KEYCLOAK_URL');

        $this->serviceWaiter->waitForService($output, $keycloakUrl.'/realms/master', successCodes: [200]);

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
        $defaultRoles = [];
        foreach ($this->symfonyApplications as $app) {
            $defaultRoles[$app] = [
                'description' => sprintf('Access to %s app', ucwords($app)),
            ];

            $adminSubRoles[$app.'-admin'] = [
                'description' => sprintf('Admin access for %s', ucwords($app)),
                'roles' => [
                    $app => $defaultRoles[$app],
                ],
            ];
        }

        $defaultRolesWrapperRole = 'default-roles-'.$this->keycloakManager->getRealmName();
        $roleHierarchy = [
            KeycloakInterface::ROLE_ADMIN => [
                'description' => 'Can do anything',
                'roles' => $adminSubRoles,
            ],
            KeycloakInterface::ROLE_TECH => [
                'description' => 'Access to Dev/Ops tools',
                'roles' => [],
            ],
            $defaultRolesWrapperRole => [
                'roles' => $defaultRoles,
            ],
        ];
        $this->keycloakManager->createRoleHierarchy($roleHierarchy);

        $this->configureClients();

        $defaultAdminUsername = EnvHelper::getEnvOrThrow('DEFAULT_ADMIN_USERNAME');
        $defaultAdminEmail = EnvHelper::getEnv('DEFAULT_ADMIN_EMAIL') ?: $defaultAdminUsername;
        if (!str_contains($defaultAdminEmail, '@')) {
            $domain = EnvHelper::getEnvOrThrow('PHRASEA_DOMAIN');
            $defaultAdminEmail .= '@'.$domain;
        }

        $defaultAdmin = $this->keycloakManager->createUser([
            'username' => $defaultAdminUsername,
            'email' => $defaultAdminEmail,
            'enabled' => true,
            'emailVerified' => true,
            'firstName' => 'Admin',
            'lastName' => 'Admin',
            'credentials' => [[
                'type' => 'password',
                'value' => EnvHelper::getEnvOrThrow('DEFAULT_ADMIN_PASSWORD'),
                'temporary' => $hasTestPreset ? false : !EnvHelper::getBooleanEnv('KEYCLOAK_ADMIN_DEFINITIVE_PASSWORD'),
            ]],
        ]);

        $this->keycloakManager->addRolesToUser($defaultAdmin['id'], [
            KeycloakInterface::ROLE_ADMIN,
            KeycloakInterface::ROLE_TECH,
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

    public function synchronize(): void
    {
        $this->configureRealm();
        $this->configureClients();
    }

    private function configureClients(): void
    {
        foreach ([
            'openid',
            'groups',
        ] as $scope) {
            $this->keycloakManager->createScope($scope, [
                'type' => 'default',
            ]);
        }

        $appScopes = $this->getAppScopes();
        foreach ($appScopes as $app => $scopes) {
            foreach ($scopes as $scope) {
                $this->keycloakManager->createScope($scope, [
                    'description' => sprintf('%s in %s', $scope, ucwords($app)),
                ]);

                $roleName = sprintf('%s-admin', $app);
                $this->keycloakManager->assignRoleToScope($scope, $roleName);
            }
        }

        foreach ($this->symfonyApplications as $app) {
            $clientId = EnvHelper::getEnvOrThrow(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $baseUri = EnvHelper::getEnvOrThrow(sprintf('%s_API_URL', strtoupper($app)));

            $clientData = $this->configureClient(
                $clientId,
                EnvHelper::getEnvOrThrow(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                $baseUri,
                [
                    'serviceAccountsEnabled' => true,
                ],
                redirectUris: [
                    $baseUri.'/admin/*',
                    $baseUri.'/bundles/apiplatform/swagger-ui/oauth2-redirect.html',
                ]
            );

            $roleName = sprintf('%s-admin', $app);
            $this->keycloakManager->addServiceAccountRealmRole($clientData, $roleName);

            $this->keycloakManager->addServiceAccountClientRole($clientData, 'view-users', 'realm-management');
            foreach ($this->getAdminClientServiceAccountRoles()[$app] ?? [] as $role) {
                $this->keycloakManager->addServiceAccountClientRole($clientData, $role, 'realm-management');
            }

            foreach ($appScopes[$app] ?? [] as $scope) {
                $this->keycloakManager->addScopeToClient($scope, $clientData['id'], false);
            }
        }

        foreach ($this->frontendApplications as $app) {
            $this->configureClient(
                EnvHelper::getEnvOrThrow(sprintf('%s_CLIENT_ID', strtoupper($app))),
                null,
                EnvHelper::getEnvOrThrow(sprintf('%s_CLIENT_URL', strtoupper($app))),
                [
                    'serviceAccountsEnabled' => false,
                ]
            );
        }

        if (EnvHelper::getEnv('INDEXER_DATABOX_CLIENT_ID')) {
            $clientData = $this->keycloakManager->createClient(
                EnvHelper::getEnvOrThrow('INDEXER_DATABOX_CLIENT_ID'),
                EnvHelper::getEnvOrThrow('INDEXER_DATABOX_CLIENT_SECRET'),
                null,
                [
                    'standardFlowEnabled' => false,
                    'implicitFlowEnabled' => false,
                    'directAccessGrantsEnabled' => false,
                    'serviceAccountsEnabled' => true,
                ],
            );
            $this->keycloakManager->addServiceAccountRealmRole($clientData, 'databox-admin');

            foreach ($appScopes['databox'] as $scope) {
                $this->keycloakManager->addScopeToClient($scope, $clientData['id'], true);
            }
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
                'asset-data-template',
                'asset',
                'attribute-definition',
                'attribute-entity',
                'attribute-list',
                'attribute-policy',
                'basket',
                'collection',
                'entity-list',
                'rendition-definition',
                'rendition-policy',
                'rendition-rule',
                'integration',
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
            $this->keycloakManager->addScopeToClient($scope, $clientData['id'], true);
        }

        return $clientData;
    }

    public function configureRealm(): void
    {
        $this->keycloakManager->createRealm();

        $this->keycloakManager->putRealm([
            'displayName' => 'Phrasea Auth',
            'displayNameHtml' => EnvHelper::getEnv('KC_REALM_HTML_DISPLAY_NAME', '<div class="kc-logo-text"><span>Phrasea Auth</span></div>'),
            'registrationAllowed' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_REGISTRATION_ALLOWED'),
            'resetPasswordAllowed' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_VERIFY_EMAIL_ALLOWED'),
            'registrationEmailAsUsername' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_EMAIL_AS_USERNAME'),
            'editUsernameAllowed' => EnvHelper::getBooleanEnv('KC_REALM_LOGIN_EDIT_USERNAME'),
            'bruteForceProtected' => true,
            'failureFactor' => '30',
            'bruteForceStrategy' => 'MULTIPLE',
            'permanentLockout' => false,
            'waitIncrementSeconds' => '60',
            'maxFailureWaitSeconds' => '900',
            'maxDeltaTimeSeconds' => '43200',
            'quickLoginCheckMilliSeconds' => '1000',
            'minimumQuickLoginWaitSeconds' => '60',
            'eventsEnabled' => EnvHelper::getBooleanEnv('KC_REALM_USER_EVENT_ENABLED'),
            'eventsExpiration' => EnvHelper::getEnv('KC_REALM_USER_EVENT_EXPIRATION', '604800'),
            'eventsListeners' => ['jboss-logging'],
            'adminEventsEnabled' => EnvHelper::getBooleanEnv('KC_REALM_ADMIN_EVENT_ENABLED'),
            'adminEventsDetailsEnabled' => true,
            'ssoSessionIdleTimeout' => EnvHelper::getEnv('KC_REALM_SSO_SESSION_IDLE_TIMEOUT', '1800'),
            'ssoSessionMaxLifespan' => EnvHelper::getEnv('KC_REALM_SSO_SESSION_MAX_LIFESPAN', '36000'),
            'clientSessionIdleTimeout' => EnvHelper::getEnv('KC_REALM_CLIENT_SESSION_IDLE_TIMEOUT', '1800'),
            'clientSessionMaxLifespan' => EnvHelper::getEnv('KC_REALM_CLIENT_SESSION_MAX_LIFESPAN', '36000'),
            'offlineSessionIdleTimeout' => EnvHelper::getEnv('KC_REALM_OFFLINE_SESSION_IDLE_TIMEOUT', '2592000'),
            'offlineSessionMaxLifespanEnabled' => (bool) EnvHelper::getEnv('KC_REALM_OFFLINE_SESSION_MAX_LIFESPAN'),
            'offlineSessionMaxLifespan' => EnvHelper::getEnv('KC_REALM_OFFLINE_SESSION_MAX_LIFESPAN', '7344000'),
            'internationalizationEnabled' => true,
            'supportedLocales' => (null != EnvHelper::getEnv('KC_REALM_SUPPORTED_LOCALES')) ? explode(',', EnvHelper::getEnv('KC_REALM_SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale' => EnvHelper::getEnv('KC_REALM_DEFAULT_LOCALE', 'en'),
            'loginTheme' => 'phrasea',
            'smtpServer' => [
                'auth' => (bool) EnvHelper::getEnv('MAILER_USER'),
                'from' => EnvHelper::getEnv('MAIL_FROM', 'noreply@phrasea.io'),
                'fromDisplayName' => EnvHelper::getEnv('MAIL_FROM_DISPLAY_NAME', 'Phrasea'),
                'replyTo' => EnvHelper::getEnv('MAIL_REPLY_TO', ''),
                'replyToDisplayName' => EnvHelper::getEnv('MAIL_REPLY_TO_DISPLAY_NAME', ''),
                'envelopeFrom' => EnvHelper::getEnv('MAIL_ENVELOPE_FROM', ''),
                'host' => EnvHelper::getEnv('MAILER_HOST'),
                'port' => EnvHelper::getEnv('MAILER_PORT', '587'),
                'ssl' => EnvHelper::getBooleanEnv('MAILER_SSL'),
                'starttls' => EnvHelper::getBooleanEnv('MAILER_TLS'),
                'user' => EnvHelper::getEnv('MAILER_USER'),
                'password' => EnvHelper::getEnv('MAILER_PASSWORD'),
            ],
            'attributes' => [
                'adminEventsExpiration' => EnvHelper::getEnv('KC_REALM_ADMIN_EVENT_EXPIRATION', '604800'),
            ],
        ]);
    }

    private function configureDefaultClientScopes(): void
    {
        $this->configureRolesMapping();
        $this->configureGroupsMapping();
    }

    private function configureGroupsMapping(): void
    {
        $mainScopeName = 'groups';
        $this->keycloakManager->createScope($mainScopeName, [
            'type' => 'default',
            'description' => 'OpenID Connect scope for adding user groups to the access token',
            'attributes' => [
                'include.in.token.scope' => 'false',
            ],
        ]);
        $mainScope = $this->keycloakManager->getDefaultClientScopeByName($mainScopeName);

        if (null === $mainScope) {
            throw new \InvalidArgumentException(sprintf('Scope named "%s" not found in client scopes', $mainScopeName));
        }
        $scopeId = $mainScope['id'];

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

    private function configureRolesMapping(): void
    {
        $rolesScope = $this->keycloakManager->getDefaultClientScopeByName('roles');
        if (null === $rolesScope) {
            throw new \InvalidArgumentException('Scope named "roles" not found in client scopes');
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
    }
}
