<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

final readonly class RoleMapper
{
    public function __construct(
        private array $mapping = [
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
