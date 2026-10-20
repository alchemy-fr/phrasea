<?php

declare(strict_types=1);

namespace App\Parade;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Entity\Parade\Parade;

final readonly class ParadeFixedClientsApplier
{
    public function __construct(
        private KeycloakClient $keycloakClient,
        private ServiceAccountClient $serviceAccountClient,
    ) {
    }

    public function apply(Parade $parade): array
    {
        $report = [];
        foreach ([
            'mainClient' => fn (string $accessToken): array => $this->ensureMainClient($accessToken, $parade),
            'serviceAccount' => fn (string $accessToken): array => $this->ensureServiceAccount($accessToken, $parade),
        ] as $key => $step) {
            try {
                $report[$key] = $this->serviceAccountClient->executeWithAccessToken($step);
            } catch (\Throwable $e) {
                $report[$key] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $report;
    }

    private function ensureMainClient(string $accessToken, Parade $parade): array
    {
        $clientId = $parade->getMainClientId();
        $url = rtrim((string) $parade->getUrl(), '/');
        if ('' === $url) {
            return ['clientId' => $clientId, 'status' => 'skipped', 'error' => 'Parade has no public URL.'];
        }
        $parts = parse_url($url);
        $origin = sprintf('%s://%s%s', $parts['scheme'] ?? 'https', $parts['host'] ?? '', isset($parts['port']) ? ':'.$parts['port'] : '');
        $expected = [
            'clientId' => $clientId,
            'protocol' => 'openid-connect',
            'publicClient' => true,
            'clientAuthenticatorType' => 'none',
            'standardFlowEnabled' => true,
            'implicitFlowEnabled' => false,
            'directAccessGrantsEnabled' => false,
            'serviceAccountsEnabled' => false,
        ];

        $current = $this->keycloakClient->findClientByClientId($accessToken, $clientId);
        if (null === $current) {
            $this->keycloakClient->createClient($accessToken, [
                ...$expected,
                'redirectUris' => [$url.'/*'],
                'webOrigins' => [$origin],
                'attributes' => ['pkce.code.challenge.method' => 'S256'],
            ]);

            return ['clientId' => $clientId, 'status' => 'created'];
        }

        $this->keycloakClient->updateClient($accessToken, $current['id'], [
            ...$expected,
            'redirectUris' => array_values(array_unique([...($current['redirectUris'] ?? []), $url.'/*'])),
            'webOrigins' => array_values(array_unique([...($current['webOrigins'] ?? []), $origin])),
            'attributes' => ['pkce.code.challenge.method' => 'S256'],
        ]);

        return ['clientId' => $clientId, 'status' => 'conformant'];
    }

    private function ensureServiceAccount(string $accessToken, Parade $parade): array
    {
        $step = function (string $label, callable $operation): mixed {
            try {
                return $operation();
            } catch (\Throwable $e) {
                throw new \RuntimeException(sprintf('%s: %s', $label, $e->getMessage()), 0, $e);
            }
        };

        $clientId = $parade->getServiceAccountClientId();
        $client = $step('find client', fn () => $this->keycloakClient->findClientByClientId($accessToken, $clientId));
        if (null === $client) {
            return ['clientId' => $clientId, 'status' => 'missing', 'error' => 'Service account client not found: create it with its secret, then save again.'];
        }
        if (true !== ($client['serviceAccountsEnabled'] ?? false)) {
            return ['clientId' => $clientId, 'status' => 'failed', 'error' => 'The client has no service account.'];
        }

        $user = $step('read service account user', fn () => $this->keycloakClient->getServiceAccountUser($accessToken, $client['id']));
        $changes = [];
        $missing = [];

        $realmRoleName = $parade->getServiceAccountRealmRole();
        $held = $step('read realm roles', fn () => array_column($this->keycloakClient->getUserRealmRoleMappings($accessToken, $user['id']), 'name'));
        if (!in_array($realmRoleName, $held, true)) {
            $realmRole = $step('find realm role', fn () => $this->keycloakClient->findAvailableRealmRole($accessToken, $user['id'], $realmRoleName));
            if (null === $realmRole) {
                $missing[] = 'realm role '.$realmRoleName;
            } else {
                $step('assign realm role', fn () => $this->keycloakClient->addUserRealmRoles($accessToken, $user['id'], [['id' => $realmRole['id'], 'name' => $realmRole['name']]]));
                $changes[] = 'realm role '.$realmRoleName;
            }
        }

        $realmManagement = $step('find realm-management', fn () => $this->keycloakClient->findClientByClientId($accessToken, 'realm-management'));
        if (null !== $realmManagement) {
            $heldClientRoles = $step('read realm-management roles', fn () => array_column($this->keycloakClient->getUserClientRoleMappings($accessToken, $user['id'], $realmManagement['id']), 'name'));
            foreach ($parade->getSettingList('serviceAccountClientRoles') as $roleName) {
                if (in_array($roleName, $heldClientRoles, true)) {
                    continue;
                }
                $role = $step('find realm-management/'.$roleName, fn () => $this->keycloakClient->findAvailableClientRole($accessToken, $user['id'], $realmManagement['id'], $roleName));
                if (null === $role) {
                    $missing[] = 'realm-management/'.$roleName;
                    continue;
                }
                $step('assign realm-management/'.$roleName, fn () => $this->keycloakClient->addUserClientRoles($accessToken, $user['id'], $realmManagement['id'], [['id' => $role['id'], 'name' => $role['name']]]));
                $changes[] = 'realm-management/'.$roleName;
            }
        }

        $scopes = $step('read client scopes', fn () => array_column($this->keycloakClient->getClientScopes($accessToken), 'id', 'name'));
        $assigned = $step('read assigned scopes', fn () => [
            ...array_column($this->keycloakClient->getClientAssignedScopes($accessToken, $client['id'], 'default'), 'name'),
            ...array_column($this->keycloakClient->getClientAssignedScopes($accessToken, $client['id'], 'optional'), 'name'),
        ]);
        foreach ($parade->getSettingList('serviceAccountScopes') as $scopeName) {
            if (in_array($scopeName, $assigned, true)) {
                continue;
            }
            if (!isset($scopes[$scopeName])) {
                $missing[] = 'scope '.$scopeName;
                continue;
            }
            $step('attach scope '.$scopeName, fn () => $this->keycloakClient->addClientOptionalScope($accessToken, $client['id'], $scopes[$scopeName]));
            $changes[] = 'scope '.$scopeName;
        }

        return [
            'clientId' => $clientId,
            'status' => [] === $missing ? 'conformant' : 'incomplete',
            'changes' => $changes,
            'missing' => $missing,
        ];
    }
}
