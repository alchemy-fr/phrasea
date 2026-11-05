<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractVoter extends Voter
{
    use SecurityAwareTrait;

    final public const string CREATE = 'CREATE';
    final public const string LIST = 'LIST';
    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';
    final public const string OPERATOR = 'OPERATOR';
    final public const string OWNER = 'OWNER';

    private PermissionManager $permissionManager;
    private ObjectMapping $objectMapping;
    private PermissionRepositoryInterface $permissionRepository;
    private UserRepositoryInterface $aclUserRepository;

    #[Required]
    public function setAclServices(
        PermissionManager $permissionManager,
        ObjectMapping $objectMapping,
        PermissionRepositoryInterface $permissionRepository,
        UserRepositoryInterface $aclUserRepository,
    ): void {
        $this->permissionManager = $permissionManager;
        $this->objectMapping = $objectMapping;
        $this->permissionRepository = $permissionRepository;
        $this->aclUserRepository = $aclUserRepository;
    }

    protected function hasAcl(array|int $attribute, AclObjectInterface $subject, TokenInterface $token, bool $ownershipGrants = true): bool
    {
        $user = $token->getUser();
        if ($user instanceof AclUserInterface) {
            return $this->permissionManager->isGranted($user, $subject, $attribute, $ownershipGrants);
        }

        return false;
    }

    /**
     * Whether the metadata is set on an ACE of the whole object type (without object id).
     *
     * @param class-string $entityClass
     */
    protected function hasTypeMetadata(int|string $metadata, string $entityClass, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AclUserInterface) {
            return false;
        }

        $aces = $this->permissionRepository->getAces(
            $user->getId(),
            $this->aclUserRepository->getAclGroupsId($user),
            $this->objectMapping->getObjectKey($entityClass),
            null,
        );

        foreach ($aces as $ace) {
            if (in_array($metadata, $ace->getMetadata(), true)) {
                return true;
            }
        }

        return false;
    }

    protected function getMetadata(AclObjectInterface $subject, TokenInterface $token): array
    {
        $metadata = [];
        $user = $token->getUser();
        if ($user instanceof AclUserInterface) {
            $aces = $this->permissionManager->getAces($user, $subject);
            foreach ($aces as $ace) {
                $metadata = array_merge($metadata, $ace->getMetadata());
            }
        }

        return $metadata;
    }

    protected function hasMetadata(int|string $metadata, AclObjectInterface $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if ($user instanceof AclUserInterface) {
            $aces = $this->permissionManager->getAces($user, $subject);
            foreach ($aces as $ace) {
                if (in_array($metadata, $ace->getMetadata(), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Reads the roles of the voted token instead of calling Security::isGranted():
     * a nested decision shares the outer AccessDecision, so its RoleVoter reasons
     * ("The user doesn't have ROLE_ADMIN.") would end up in every denial message.
     */
    protected function isAdmin(TokenInterface $token): bool
    {
        return in_array(JwtUser::ROLE_ADMIN, $token->getRoleNames(), true);
    }

    protected function isAuthenticated(): bool
    {
        return $this->security->isGranted(JwtUser::IS_AUTHENTICATED_FULLY);
    }
}
