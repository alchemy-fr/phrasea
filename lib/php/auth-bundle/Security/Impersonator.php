<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Builds the user an admin acts as. The resulting user carries only the
 * target's roles and groups: the admin's own privileges are dropped, so
 * permissions are evaluated exactly as for the target.
 */
final readonly class Impersonator
{
    final public const string HEADER = 'X-Impersonate-User';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleMapper $roleMapper,
        private bool $enabled = false,
        private array $requiredRoles = [],
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function canImpersonate(JwtUser $user): bool
    {
        return $this->enabled
            && !$user->isImpersonated()
            && in_array(JwtUser::ROLE_ADMIN, $user->getRoles(), true);
    }

    public function impersonate(JwtUser $impersonator, string $targetId): JwtUser
    {
        if (!$this->enabled) {
            throw new AccessDeniedHttpException('Impersonation is disabled');
        }

        if (!$this->canImpersonate($impersonator)) {
            throw new AccessDeniedHttpException('Only admins can impersonate users');
        }

        $identity = $this->getIdentity($targetId);

        return new JwtUser(
            $impersonator->getJwt(),
            $targetId,
            $identity['username'],
            $this->roleMapper->getRoles($identity['roles']),
            $identity['groups'],
            $impersonator->getScopes(),
            $impersonator->getId(),
        );
    }

    /**
     * @return array{id: string, username: string, email: ?string, firstName: ?string, lastName: ?string, roles: string[], groups: string[]}
     */
    public function getIdentity(string $targetId): array
    {
        if (!preg_match('#^[a-zA-Z0-9-]{1,64}$#', $targetId)) {
            throw new AccessDeniedHttpException('Invalid impersonation target');
        }

        $target = $this->userRepository->getUser($targetId);
        if (null === $target || false === ($target['enabled'] ?? true)) {
            throw new AccessDeniedHttpException('Impersonation target not found');
        }

        $idpRoles = $this->userRepository->getUserRoles($targetId);
        foreach ($this->requiredRoles as $requiredRole) {
            if (!in_array($requiredRole, $idpRoles, true)) {
                throw new AccessDeniedHttpException('Impersonation target is missing required role: '.$requiredRole);
            }
        }

        return [
            'id' => $targetId,
            'username' => $target['username'] ?? $targetId,
            'email' => $target['email'] ?? null,
            'firstName' => $target['firstName'] ?? null,
            'lastName' => $target['lastName'] ?? null,
            'roles' => $idpRoles,
            'groups' => $this->userRepository->getUserGroupIds($targetId),
        ];
    }
}
