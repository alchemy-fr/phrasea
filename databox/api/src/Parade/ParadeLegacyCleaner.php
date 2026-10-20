<?php

declare(strict_types=1);

namespace App\Parade;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeLoginClient;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ParadeLegacyCleaner
{
    final public const string LEGACY_ROLE_PREFIX = 'parade-';

    public function __construct(
        private KeycloakClient $keycloakClient,
        private ServiceAccountClient $serviceAccountClient,
        private EntityManagerInterface $em,
    ) {
    }

    public function inventory(Parade $parade): array
    {
        return $this->serviceAccountClient->executeWithAccessToken(fn (string $accessToken): array => $this->collect($accessToken, $parade));
    }

    public function clean(Parade $parade): array
    {
        return $this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($parade): array {
            $inventory = $this->collect($accessToken, $parade);
            $done = [];
            $failed = [];
            $run = function (string $label, callable $operation) use (&$done, &$failed): void {
                try {
                    $operation();
                    $done[] = $label;
                } catch (\Throwable $e) {
                    $failed[] = sprintf('%s: %s', $label, $e->getMessage());
                }
            };

            foreach ($inventory['groups'] as $group) {
                $run('group '.$group['path'], fn () => $this->keycloakClient->deleteGroup($accessToken, $group['id']));
            }
            foreach ($inventory['clients'] as $client) {
                $run('client '.$client['clientId'], fn () => $this->keycloakClient->deleteClient($accessToken, $client['id']));
            }
            if (null !== $inventory['mainClient'] && [] !== $inventory['mainClientAttributes']) {
                $main = $inventory['mainClient'];
                $run(
                    sprintf('attributes %s of %s', implode(', ', $inventory['mainClientAttributes']), $main['clientId']),
                    fn () => $this->keycloakClient->updateClient($accessToken, $main['id'], [
                        'clientId' => $main['clientId'],
                        'attributes' => (object) array_fill_keys($inventory['mainClientAttributes'], ''),
                    ]),
                );
            }
            if (null !== $inventory['mainClient']) {
                foreach ($inventory['roles'] as $roleName) {
                    $run(
                        sprintf('role %s of %s', $roleName, $inventory['mainClient']['clientId']),
                        fn () => $this->keycloakClient->deleteClientRole($accessToken, $inventory['mainClient']['id'], $roleName),
                    );
                }
            }

            return ['done' => $done, 'failed' => $failed];
        });
    }

    private function collect(string $accessToken, Parade $parade): array
    {
        $isMarked = fn (array $node): bool => ($node['attributes'][$parade->getOwnerAttribute()][0] ?? null) === $parade->getOwnerValue();

        $groups = [];
        $root = $this->keycloakClient->findGroup($accessToken, $parade->getRootGroupName());
        if (null !== $root) {
            if (!$isMarked($root)) {
                $groups[] = ['id' => $root['id'], 'path' => $root['path'] ?? '/'.$parade->getRootGroupName()];
            } else {
                $visit = function (array $parent) use (&$visit, &$groups, $accessToken, $isMarked): void {
                    foreach ($this->keycloakClient->getGroupChildren($accessToken, $parent['id']) as $child) {
                        if ($isMarked($child)) {
                            $visit($child);
                        } else {
                            $groups[] = ['id' => $child['id'], 'path' => $child['path'] ?? $child['name']];
                        }
                    }
                };
                $visit($root);
            }
        }

        $mainClientId = $parade->getMainClientId();
        $clients = [];
        foreach ($this->keycloakClient->searchClients($accessToken, $mainClientId.'--') as $client) {
            if (($client['attributes'][$parade->getOwnerAttribute()] ?? null) !== $parade->getOwnerValue()) {
                $clients[] = ['id' => $client['id'], 'clientId' => $client['clientId']];
            }
        }

        $main = $this->keycloakClient->findClientByClientId($accessToken, $mainClientId);
        $attributes = [];
        $roles = [];
        if (null !== $main) {
            $rootApplied = $this->em->getRepository(ParadeLoginClient::class)->findOneBy(['page' => ParadeLoginClient::ROOT_PAGE]);
            if (null === $rootApplied || 'applied' !== $rootApplied->getStatus()) {
                $attributes = array_values(array_filter(
                    array_keys($main['attributes'] ?? []),
                    fn (string $key): bool => str_starts_with($key, ParadeLoginClientApplier::ATTRIBUTE_PREFIX) && '' !== ($main['attributes'][$key] ?? ''),
                ));
            }
            $roles = array_values(array_filter(
                array_column($this->keycloakClient->getClientRoles($accessToken, $main['id']), 'name'),
                fn (string $name): bool => str_starts_with($name, self::LEGACY_ROLE_PREFIX),
            ));
        }

        return [
            'groups' => $groups,
            'clients' => $clients,
            'mainClient' => null !== $main ? ['id' => $main['id'], 'clientId' => $main['clientId']] : null,
            'mainClientAttributes' => $attributes,
            'roles' => $roles,
        ];
    }
}
