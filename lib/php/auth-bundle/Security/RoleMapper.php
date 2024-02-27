<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;

final readonly class RoleMapper
{
    public function __construct(
        private string $appName,
        private array $mapping = [
            'super-admin' => SuperAdminVoter::ROLE,
            'admin' => JwtUser::ROLE_ADMIN,
            'tech' => JwtUser::ROLE_TECH,
        ],
    )
    {
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
