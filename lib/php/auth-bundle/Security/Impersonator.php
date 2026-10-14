<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Builds the user an impersonator acts as. The resulting user carries only
 * the target's roles and groups: the impersonator's own privileges are
 * dropped, so permissions are evaluated exactly as for the target.
 *
 * Impersonating requires the Keycloak "impersonation" role of the
 * "realm-management" client (see JwtExtractor).
 */
final readonly class Impersonator
{
    final public const string HEADER = 'X-Impersonate-User';
    final public const string KEYCLOAK_CLIENT = 'realm-management';
    final public const string KEYCLOAK_ROLE = 'impersonation';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleMapper $roleMapper,
        private array $requiredRoles = [],
    ) {
    }

    public function canImpersonate(JwtUser $user): bool
    {
        return !$user->isImpersonated()
            && in_array(JwtUser::ROLE_IMPERSONATOR, $user->getRoles(), true);
    }

    public function impersonate(JwtUser $impersonator, string $targetId): JwtUser
    {
        if (!$this->canImpersonate($impersonator)) {
            throw new AccessDeniedHttpException('Missing impersonation permission');
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
