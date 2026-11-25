<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\Impersonator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The identity of the owner of an integration, read from Keycloak as for an impersonation.
 *
 * Kept for TTL seconds: an upload link form reads the entity values at each keystroke, and each read
 * would otherwise take three calls to the Keycloak admin API. A change of the roles or groups of the owner,
 * or its deactivation, is thus seen up to TTL seconds late. A refused owner (unknown, disabled) is not kept.
 */
final readonly class IntegrationOwnerProvider
{
    final public const int TTL = 60;

    public function __construct(
        private Impersonator $impersonator,
        #[Autowire(service: 'integration_owner.cache')]
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return array{username: string, roles: string[], groups: string[]} the roles are the Keycloak ones
     */
    public function getOwner(string $ownerId): array
    {
        return $this->cache->get(hash('xxh128', $ownerId), function (ItemInterface $item) use ($ownerId): array {
            $item->expiresAfter(self::TTL);
            $identity = $this->impersonator->getIdentity($ownerId);

            return [
                'username' => $identity['username'],
                'roles' => $identity['roles'],
                'groups' => $identity['groups'],
            ];
        });
    }
}
