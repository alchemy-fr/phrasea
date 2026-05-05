<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class WorkspaceVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'workspace:';
    final public const string CREATE_COLLECTION = 'CREATE_COLLECTION';
    final public const string MANAGER_USERS = 'MANAGER_USERS';

    private CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace && !is_numeric($attribute);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return !is_numeric($attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Workspace::class, true);
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->cache->get(sprintf('%s,%s,%s', $attribute, $subject->getId(), spl_object_id($token)), function () use ($attribute, $subject, $token) {
            return $this->doVote($attribute, $subject, $token);
        });
    }

    private function doVote(string $attribute, Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isCreator = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        return match ($attribute) {
            // Create a new Workspace
            AbstractVoter::CREATE => $this->isAdmin(),
            self::CREATE_COLLECTION => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CREATE,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            AbstractVoter::READ => $isCreator()
                || $subject->isPublic()
                || $this->hasAcl([
                    PermissionInterface::VIEW,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin()
            ,
            AbstractVoter::EDIT => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::EDIT,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            AbstractVoter::DELETE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                ], $subject, $token)
                || $this->isAdmin(),
            // Add or remove users/groups to workspace (only VIEW permission)
            // TODO implement UI to add/remove users/groups on client
            self::MANAGER_USERS => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_MANAGE_USERS, $subject, $token)
                || $this->isAdmin(),
            AbstractVoter::EDIT_PERMISSIONS, AbstractVoter::OWNER => $isCreator()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->isAdmin(),
            AssetContainerVoterInterface::ASSET_VIEW => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_VIEW,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            AssetContainerVoterInterface::ASSET_CREATE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_CREATE,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            AssetContainerVoterInterface::ASSET_SHARE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            AssetContainerVoterInterface::ASSET_EDIT_ATTRIBUTES => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_EDIT => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_DELETE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_OWNER => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_EDIT_PERMISSIONS => $isCreator()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token),
            default => false,
        };
    }
}
