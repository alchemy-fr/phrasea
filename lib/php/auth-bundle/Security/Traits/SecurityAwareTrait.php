<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Traits;

use Alchemy\AuthBundle\Security\JwtInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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

    public function hasScope(string $scope, ?string $scopePrefix = null, ?bool $applyHierarchy = null): bool
    {
        $token = $this->security->getToken();
        if (!$token instanceof JwtToken) {
            return false;
        }

        return $this->tokenHasScope($token, $scope, $scopePrefix, $applyHierarchy ?? null !== $scopePrefix);
    }

    protected function tokenHasScope(TokenInterface $token, string $scope, ?string $scopePrefix, bool $applyHierarchy = true): bool
    {
        if (empty($scope)) {
            throw new \InvalidArgumentException('Scope cannot be empty');
        }
        if (!$token instanceof JwtToken) {
            return false;
        }

        $tokenScopes = $token->getScopes();
        $scopes = $applyHierarchy ? $this->getScopesFromHierarchy($scope) : [$scope];

        $scopes = array_map(fn (string $scope): string => ($scopePrefix ?? '').strtolower($scope), $scopes);

        return !empty(array_intersect($scopes, $tokenScopes));
    }

    private function getScopesFromHierarchy(string $attribute): array
    {
        $scopes = [$attribute];

        $this->visitScopeHierarchy($attribute, $scopes);

        return array_unique($scopes);
    }

    private function visitScopeHierarchy(string $attribute, array &$scopes): void
    {
        $subScopes = $this->getScopeHierarchy()[$attribute] ?? [];
        foreach ($subScopes as $subScope) {
            if (in_array($subScope, $scopes, true)) {
                continue;
            }
            $scopes[] = $subScope;
            $this->visitScopeHierarchy($subScope, $scopes);
        }
    }

    protected function getScopeHierarchy(): array
    {
        return [
            AbstractVoter::CREATE => [AbstractVoter::EDIT],
            AbstractVoter::LIST => [],
            AbstractVoter::READ => [AbstractVoter::EDIT],
            AbstractVoter::EDIT => [AbstractVoter::OPERATOR],
            AbstractVoter::DELETE => [AbstractVoter::OPERATOR],
            AbstractVoter::EDIT_PERMISSIONS => [AbstractVoter::OWNER],
            AbstractVoter::OPERATOR => [AbstractVoter::OWNER],
        ];
    }

    public function hasRole(string $role): bool
    {
        $token = $this->security->getToken();
        if (!$token instanceof JwtToken) {
            return false;
        }

        $roles = $this->roleMapper->getRoles([$role]);
        $tokenRoles = $token->getRoleNames();
        if (empty($tokenRoles)) {
            return false;
        }

        foreach ($roles as $r) {
            if (in_array($r, $tokenRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
