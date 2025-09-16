<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Security\ScopeTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractVoter extends Voter
{
    use ScopeTrait;

    final public const string CREATE = 'CREATE';
    final public const string LIST = 'LIST';
    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';
    final public const string OPERATOR = 'OPERATOR';
    final public const string OWNER = 'OWNER';

    protected EntityManagerInterface $em;
    private PermissionManager $permissionManager;

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
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
        throw new \RuntimeException(sprintf('%s does not implement %s', static::class, __FUNCTION__));
    }
}
