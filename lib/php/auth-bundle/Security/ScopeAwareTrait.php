<?php

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Security\Token\JwtToken;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait ScopeAwareTrait
{
    protected Security $security;

    protected function hasScope(string $scope, string $scopePrefix, bool $applyHierarchy = true): bool
    {
        $token = $this->security->getToken();
        if (null === $token) {
            return false;
        }

        return $this->tokenHasScope($token, $scope, $scopePrefix, $applyHierarchy);
    }

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function tokenHasScope(TokenInterface $token, string $scope, string $scopePrefix, bool $applyHierarchy = true): bool
    {
        if (empty($scope)) {
            throw new \InvalidArgumentException('Scope cannot be empty');
        }
        if (!$token instanceof JwtToken) {
            return false;
        }

        $tokenScopes = $token->getScopes();
        $scopes = $applyHierarchy ? $this->getScopesFromHierarchy($scope) : [$scope];

        $scopes = array_map(fn (string $scope): string => $scopePrefix.strtolower($scope), $scopes);

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
}
