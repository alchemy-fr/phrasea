<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractVoter extends Voter
{
    final public const string CREATE = 'CREATE';
    final public const string LIST = 'LIST';
    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';
    final public const string OPERATOR = 'OPERATOR';
    final public const string OWNER = 'OWNER';

    protected EntityManagerInterface $em;
    protected Security $security;
    private PermissionManager $permissionManager;

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    #[Required]
    public function setPermissionManager(PermissionManager $permissionManager): void
    {
        $this->permissionManager = $permissionManager;
    }

    protected function hasAcl(int $attribute, AclObjectInterface $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if ($user instanceof AclUserInterface) {
            return $this->permissionManager->isGranted($user, $subject, $attribute);
        }

        return false;
    }

    protected function hasScope(TokenInterface $token, string $scope, ?string $scopePrefix = null, bool $applyHierarchy = true): bool
    {
        if (!$token instanceof JwtToken) {
            return false;
        }

        $tokenScopes = $token->getScopes();
        $scopes = $applyHierarchy ? $this->getScopesFromHierarchy($scope) : [$scope];

        $scopePrefix ??= static::getScopePrefix();
        $scopes = array_map(fn (string $scope): string => $scopePrefix . strtolower($scope), $scopes);

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
            self::CREATE => [self::EDIT],
            self::LIST => [],
            self::READ => [self::EDIT],
            self::EDIT => [self::OPERATOR],
            self::DELETE => [self::OPERATOR],
            self::EDIT_PERMISSIONS => [self::OWNER],
            self::OPERATOR => [self::OWNER],
        ];
    }

    protected function isAdmin(): bool
    {
        return $this->security->isGranted(JwtUser::ROLE_ADMIN);
    }

    protected function isAuthenticated(): bool
    {
        return $this->security->isGranted(JwtUser::IS_AUTHENTICATED_FULLY);
    }

    public static function getScopePrefix(): string
    {
        throw new \RuntimeException(sprintf('%s does not implement %s', static::class, __FUNCTION__));;
    }
}
