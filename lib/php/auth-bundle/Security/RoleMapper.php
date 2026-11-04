<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

/**
 * Maps the realm roles of the identity provider to Symfony roles.
 *
 * Only mapped roles reach the application: an application extends the default
 * mapping with its own roles through the `alchemy_auth.role_mapping` configuration.
 */
final readonly class RoleMapper
{
    final public const array DEFAULT_MAPPING = [
        'admin' => JwtUser::ROLE_ADMIN,
        'tech' => JwtUser::ROLE_TECH,
    ];

    public function __construct(
        private string $appName,
        private array $mapping = self::DEFAULT_MAPPING,
    ) {
    }

    public function getRoles(array $idpRoles): array
    {
        return array_values(array_unique(array_filter(array_map(function (string $role): ?string {
            if ($role === sprintf('%s-admin', $this->appName)) {
                return JwtUser::ROLE_ADMIN;
            }

            return $this->mapping[$role] ?? null;
        }, $idpRoles))));
    }
}
