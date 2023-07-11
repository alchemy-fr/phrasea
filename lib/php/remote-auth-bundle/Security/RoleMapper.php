<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

final class RoleMapper
{
    public function __construct(
        private readonly array $mapping = [
            'admin' => 'ROLE_ADMIN',
        ]
    )
    {

    }

    public function getRoles(array $idpRoles): array
    {
        return array_filter(array_map(function (string $role): ?string {
            return $this->mapping[$role] ?? null;
        }, $idpRoles));
    }
}
