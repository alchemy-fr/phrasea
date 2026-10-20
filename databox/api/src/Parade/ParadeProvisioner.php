<?php

declare(strict_types=1);

namespace App\Parade;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;
use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use App\Config\Schema\ParadeConfigSchema;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeTenant;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ParadeProvisioner
{
    final public const array RESERVED_TENANT_SLUGS = ['super-admins', 'admins', 'techniques', 'fonctionnels'];

    public function __construct(
        private KeycloakClient $keycloakClient,
        private ServiceAccountClient $serviceAccountClient,
        private ConfiguratorEntryRepository $configuratorEntryRepository,
        private ManagerRegistry $doctrine,
        private MessageBusInterface $bus,
    ) {
    }

    public function provisionParade(Parade $parade, bool $adopt = false): void
    {
        $parade->setGroups($this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($parade, $adopt): array {
            $root = $this->ensureRoot($accessToken, $parade, $adopt);

            return ['root' => $root['id'], ...$this->ensureRoleGroups($accessToken, $parade, $root['id'], $this->siteGroups($parade), [], $adopt)];
        }));
    }

    public function provisionTenant(ParadeTenant $tenant, bool $adopt = false): void
    {
        $parade = $this->parade();
        if (in_array($tenant->getSlug(), [$parade->getSuperAdminsGroupName(), ...array_values($this->tenantGroups($parade))], true)) {
            throw new \InvalidArgumentException(sprintf('The slug "%s" is reserved for the Parade groups.', $tenant->getSlug()));
        }

        $tenant->setGroups($this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($parade, $tenant, $adopt): array {
            $root = $this->ensureRoot($accessToken, $parade, $adopt);
            $owner = ['paradeTenant' => $tenant->getSlug()];
            $tenantGroup = $this->keycloakClient->ensureGroup(
                $accessToken,
                $tenant->getSlug(),
                $root['id'],
                $this->ownership($parade, $owner),
                $adopt,
            );

            return ['tenant' => $tenantGroup['id'], ...$this->ensureRoleGroups($accessToken, $parade, $tenantGroup['id'], $this->tenantGroups($parade), $owner, $adopt)];
        }));
    }

    public function parade(): Parade
    {
        return $this->doctrine->getRepository(Parade::class)->findOneBy([]) ?? new Parade();
    }

    private function siteGroups(Parade $parade): array
    {
        return ['superAdmin' => $parade->getSuperAdminsGroupName(), ...$this->tenantGroups($parade)];
    }

    private function tenantGroups(Parade $parade): array
    {
        return [
            'admin' => $parade->getAdminsGroupName(),
            'technical' => $parade->getTechnicalGroupName(),
            'functional' => $parade->getFunctionalGroupName(),
        ];
    }

    public function listMembers(string $groupId): array
    {
        return $this->serviceAccountClient->executeWithAccessToken(
            fn (string $accessToken): array => $this->keycloakClient->getGroupMembers($accessToken, $groupId, 0, 500)
        );
    }

    public function addMember(string $groupId, string $userId): void
    {
        $this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($groupId, $userId): void {
            $this->keycloakClient->addUserToGroup($accessToken, $userId, $groupId);
        });
    }

    public function removeMember(string $groupId, string $userId): void
    {
        $this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($groupId, $userId): void {
            $this->keycloakClient->removeUserFromGroup($accessToken, $userId, $groupId);
        });
    }

    public function publishState(Parade $parade): void
    {
        $em = $this->doctrine->getManagerForClass(ConfiguratorEntry::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('No entity manager for configurator entries');
        }

        $this->setEntry($em, ParadeConfigSchema::ENABLED_KEY, $parade->isEnabled() ? 'true' : 'false');
        $this->setEntry($em, ParadeConfigSchema::URL_KEY, $parade->getUrl());
        $em->flush();

        $this->bus->dispatch(new DeployConfig());
    }

    private function ensureRoot(string $accessToken, Parade $parade, bool $adopt): array
    {
        return $this->keycloakClient->ensureGroup($accessToken, $parade->getRootGroupName(), null, $this->ownership($parade), $adopt);
    }

    private function ensureRoleGroups(string $accessToken, Parade $parade, string $parentId, array $groupNames, array $owner, bool $adopt): array
    {
        $groups = [];
        foreach ($groupNames as $role => $name) {
            $groups[$role] = $this->keycloakClient->ensureGroup(
                $accessToken,
                $name,
                $parentId,
                $this->ownership($parade, [...$owner, 'paradeRole' => $role]),
                $adopt,
            )['id'];
        }

        return $groups;
    }

    public function ownership(Parade $parade, array $attributes = []): array
    {
        return [$parade->getOwnerAttribute() => $parade->getOwnerValue(), ...$attributes];
    }

    private function setEntry(EntityManagerInterface $em, string $name, ?string $value): void
    {
        $entry = $this->configuratorEntryRepository->findOneBy(['name' => $name]);
        if (null === $value || '' === $value) {
            if (null !== $entry) {
                $em->remove($entry);
            }

            return;
        }

        if (null === $entry) {
            $entry = new ConfiguratorEntry();
            $entry->setName($name);
            $em->persist($entry);
        }
        $entry->setValue($value);
    }
}
