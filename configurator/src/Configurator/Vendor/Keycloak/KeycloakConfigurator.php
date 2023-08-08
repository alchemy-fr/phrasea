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
        private string $keycloakRealm,
    ) {
    }

    public function configure(OutputInterface $output): void
    {
        $this->configureMail();

        foreach ([
                     KeycloakInterface::GROUP_SUPER_ADMIN => 'Can do anything',
                     KeycloakInterface::GROUP_TECH => 'Access to Dev/Ops Operations',
                     KeycloakInterface::GROUP_USER_ADMIN => 'Manage Users',
                     KeycloakInterface::GROUP_GROUP_ADMIN => 'Manage Groups',
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

        foreach ($this->symfonyApplications as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $client = $this->configureClient(
                $clientId,
                getenv(sprintf('%s_ADMIN_CLIENT_SECRET', strtoupper($app))),
                getenv(sprintf('%s_API_URL', strtoupper($app))).'/admin',
                [
                    'serviceAccountsEnabled' => true,
                ]
            );

            $this->keycloakManager->addServiceAccountRole($client, 'view-users', $this->keycloakRealm.'-realm');
            $this->keycloakManager->addServiceAccountRole($client, 'view-groups', 'account');
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
    }

    private function getAppScopes(): array
    {
        return [
            'databox' => [
                'chuck-norris',
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
            'protocolMapper' => 'oidc-group-membership-mapper',
            'config' => [
                'claim.name' => 'groups',
                'access.token.claim' => 'true',
                'userinfo.token.claim' => 'true',
                'id.token.claim' => 'true',
                'full.path' => 'false',
            ],
        ]);

        return $clientData;
    }

    private function configureMail(): void
    {
        $from = getenv('MAIL_FROM');
        $mailer = parse_url(getenv('MAILER_DSN'));

        dump($mailer);

        $this->keycloakManager->putRealm([
            'smtpServer' => [
                'auth' => '',
                'from' => $from,
                'fromDisplayName' => '',
                'host' => $mailer['host'],
                'port' => $mailer['port'],
                'replyTo' => '',
                'ssl' => false,
                'starttls' => false,
            ],
        ]);
    }
}
