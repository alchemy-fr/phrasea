<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CollectionVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'collection:';

    private CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Collection;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Collection::class, true);
    }

    /**
     * @param Collection $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->cache->get(sprintf('%s,%s,%s', $attribute, $subject->getId(), spl_object_id($token)), function (
        ) use ($attribute, $subject, $token) {
            return $this->doVote($attribute, $subject, $token);
        });
    }

    private function doVote(string $attribute, Collection $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $workspace = $subject->getWorkspace();
        if (!$this->security->isGranted(self::READ, $workspace)) {
            return false;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isWorkspaceOwnerFast = fn (): bool => $userId && $subject->getWorkspace()->getOwnerId() === $userId;
        $isWorkspaceOwnerSlow = fn (
        ): bool => $this->hasAcl(PermissionInterface::OWNER, $subject->getWorkspace(), $token);
        $isCreator = fn (
        ): bool => $userId && $subject->getOwnerId() === $userId || $subject->getWorkspace()->getOwnerId() === $userId;
        $isOwnerSlow = fn (
        ): bool => $this->hasAcl(PermissionInterface::OWNER, $subject, $token) || $this->security->isGranted(self::OWNER, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $isWorkspaceOwnerFast()
                || (
                    null !== $subject->getParent()
                    ? $this->hasAcl([
                        PermissionInterface::CREATE,
                    ], $subject->getParent(), $token)
                    : $this->security->isGranted(WorkspaceVoter::CREATE_COLLECTION, $subject->getWorkspace())
                )
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
                || $isOwnerSlow()
            ,
            // View collection name but not its assets
            self::LIST => $isCreator()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || ($subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->hasAcl(PermissionInterface::CHILD_VIEW, $subject->getWorkspace(), $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            // View collection assets
            self::READ => $isCreator()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                || $this->hasAcl([
                    PermissionInterface::VIEW,
                    PermissionInterface::CHILD_VIEW,
                ], $subject, $token)
                || $this->hasAcl(PermissionInterface::CHILD_VIEW, $subject->getWorkspace(), $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT => $isCreator()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::DELETE => $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::OWNER => $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::CREATE_ASSET => $isWorkspaceOwnerFast()
                || $this->hasAcl(PermissionInterface::CHILD_CREATE, $subject, $token, ownershipGrants: false)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
                || $this->isAdmin()
            ,
            self::EDIT_ASSET_ATTRIBUTES => $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT_ASSET => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            self::EDIT_ASSET_TAG => ($userId && $subject->getWorkspace()->getOwnerId() === $userId)
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_TAG, $subject, $token)
                || $this->parentIsGranted($attribute, $subject),

            // Edit permissions and object privacy
            self::EDIT_PERMISSIONS => $isWorkspaceOwnerFast()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
            ,

            self::SHARE_ASSET => $this->hasAcl([
                PermissionInterface::CHILD_SHARE,
            ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject),

            self::DELETE_ASSET => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                    PermissionInterface::CHILD_DELETE,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            default => false,
        };
    }

    private function parentIsGranted(mixed $attribute, Collection $subject): bool
    {
        return null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent());
    }
}
