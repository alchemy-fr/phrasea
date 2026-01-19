<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\ScopeAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractVoter extends Voter
{
    use ScopeAwareTrait;

    final public const string CREATE = 'CREATE';
    final public const string LIST = 'LIST';
    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';
    final public const string OPERATOR = 'OPERATOR';
    final public const string OWNER = 'OWNER';

    private PermissionManager $permissionManager;

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

    protected function isAdmin(): bool
    {
        return $this->security->isGranted(JwtUser::ROLE_ADMIN);
    }

    protected function isAuthenticated(): bool
    {
        return $this->security->isGranted(JwtUser::IS_AUTHENTICATED_FULLY);
    }
}
