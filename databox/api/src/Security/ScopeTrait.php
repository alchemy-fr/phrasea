<?php

namespace App\Security;

use Alchemy\AuthBundle\Security\Token\JwtToken;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait ScopeTrait
{
    protected Security $security;

    protected function hasScope(?string $scope = null, ?string $scopePrefix = null, bool $applyHierarchy = true): bool
    {
        if (empty($scope)) {
            return false;
        }

        $token = $this->security->getToken();
        if (null === $token) {
            return false;
        }

        return $this->tokenHasScope($token, $scope, $scopePrefix, $applyHierarchy);
    }

    protected function tokenHasScope(TokenInterface $token, string $scope, ?string $scopePrefix = null, bool $applyHierarchy = true): bool
    {
        if (empty($scope)) {
            return false;
        }
        if (!$token instanceof JwtToken) {
            return false;
        }

        $tokenScopes = $token->getScopes();
        $scopes = $applyHierarchy ? $this->getScopesFromHierarchy($scope) : [$scope];

        $scopePrefix ??= static::getScopePrefix();
        $scopes = array_map(fn (string $scope): string => $scopePrefix.strtolower($scope), $scopes);

        return !empty(array_intersect($scopes, $tokenScopes));
    }

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
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
}
