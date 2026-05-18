<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Traits;

use Alchemy\AuthBundle\Security\JwtInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\Attribute\Required;

trait SecurityAwareTrait
{
    protected Security $security;
    protected RoleMapper $roleMapper;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    #[Required]
    public function setRoleMapper(RoleMapper $roleMapper): void
    {
        $this->roleMapper = $roleMapper;
    }

    protected function isSuperAdmin(): bool
    {
        return $this->security->isGranted(SuperAdminVoter::ROLE);
    }

    protected function isAdmin(): bool
    {
        return $this->security->isGranted(JwtUser::ROLE_ADMIN);
    }

    protected function isGranted(mixed $attributes, mixed $subject = null): bool
    {
        return $this->security->isGranted($attributes, $subject);
    }

    protected function getTokenId(): string
    {
        if (is_object($this->security->getToken())) {
            return (string) spl_object_id($this->security->getToken());
        }

        return 'no_token';
    }

    protected function getUserCacheId(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? '__no_user__';
    }

    protected function getStrictUser(): JwtUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        return $user;
    }

    protected function getStrictUserOrOAuthClient(): JwtInterface
    {
        if (null === $user = $this->getUserOrOAuthClient()) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        return $user;
    }

    protected function getUserOrOAuthClient(): ?JwtInterface
    {
        $user = $this->security->getUser();

        if ($user instanceof JwtInterface) {
            return $user;
        }

        return null;
    }

    protected function getUser(): ?JwtUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof JwtUser) {
            return null;
        }

        return $user;
    }

    public function denyAccessUnlessGranted(mixed $attributes, mixed $subject = null, ?string $message = null): void
    {
        if (!$this->isGranted($attributes, $subject)) {
            throw new AccessDeniedException($message ?? 'Access denied.');
        }
    }

    public function hasScope(string $scope): bool
    {
        $token = $this->security->getToken();
        if (!$token instanceof JwtToken) {
            return false;
        }

        return $token->hasScope($scope);
    }

    public function hasRole(string $role): bool
    {
        $token = $this->security->getToken();
        if (!$token instanceof JwtToken) {
            return false;
        }

        if (!$token->hasAttribute('roles')) {
            return false;
        }

        $roles = $this->roleMapper->getRoles([$role]) ?? [$role];
        foreach ($roles as $r) {
            if (in_array($r, $token->getAttribute('roles'), true)) {
                return true;
            }
        }

        return false;
    }
}
