<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

final readonly class RoleMapper
{
    public function __construct(
        private string $appName,
        private array $mapping = [
            'admin' => 'ROLE_ADMIN',
        ],
    )
    {
    }

    public function getRoles(array $idpRoles): array
    {
        return array_values(array_unique(array_filter(array_map(function (string $role): ?string {
            if ($role === sprintf('%s-admin', $this->appName)) {
                return 'ROLE_ADMIN';
            }

            return $this->mapping[$role] ?? null;
        }, $idpRoles))));
    }
}
