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
        if (!$this->security->isGranted(AbstractVoter::READ, $workspace)) {
            return false;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isWorkspaceOwnerFast = fn (): bool => $userId && $workspace->getOwnerId() === $userId;
        $isWorkspaceOwnerSlow = fn (
        ): bool => $this->security->isGranted(AbstractVoter::OWNER, $workspace);
        $isCreator = fn (
        ): bool => $userId && $subject->getOwnerId() === $userId || $workspace->getOwnerId() === $userId;
        $isOwnerSlow = fn (
        ): bool => $this->security->isGranted(AbstractVoter::OWNER, $subject);

        return match ($attribute) {
            AbstractVoter::CREATE => $isWorkspaceOwnerFast()
                || (
                    null !== $subject->getParent()
                        ? $this->hasAcl([
                            PermissionInterface::CREATE,
                        ], $subject->getParent(), $token)
                        : $this->security->isGranted(WorkspaceVoter::CREATE_COLLECTION, $workspace)
                )
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
                || $isOwnerSlow()
            ,
            // View collection name but not necessary its assets
            AbstractVoter::READ => (!$subject->isDeleted() || $this->security->isGranted(AbstractVoter::DELETE, $subject))
                && (
                    $isCreator()
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                    || ($subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                    || $this->hasAcl([
                        PermissionInterface::VIEW,
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->parentIsGranted($attribute, $subject)
                    || $isWorkspaceOwnerSlow()
                    || $isOwnerSlow()
                )
            ,
            AssetContainerVoterInterface::ASSET_VIEW => (!$subject->isDeleted() || $this->security->isGranted(AbstractVoter::DELETE, $subject))
                && (
                    $isCreator()
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                    || $this->parentIsGranted($attribute, $subject)
                    || $this->hasAcl([
                        PermissionInterface::VIEW,
                        PermissionInterface::CHILD_VIEW,
                        PermissionInterface::CHILD_OWNER,
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->isAdmin()
                )
            ,
            AbstractVoter::EDIT => $isCreator()
                || $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::EDIT,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
            ,
            AbstractVoter::DELETE => $isCreator()
                || $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
            ,
            AbstractVoter::OWNER => $isCreator()
                || $isWorkspaceOwnerFast()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
            ,
            AssetContainerVoterInterface::ASSET_CREATE => $isWorkspaceOwnerFast()
                || $this->hasAcl(PermissionInterface::CHILD_CREATE, $subject, $token, ownershipGrants: false)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
                || $this->isAdmin()
            ,
            AssetContainerVoterInterface::ASSET_EDIT_ATTRIBUTES => $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            AssetContainerVoterInterface::ASSET_EDIT => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            // Edit permissions and object privacy
            AbstractVoter::EDIT_PERMISSIONS => $isWorkspaceOwnerFast()
                || (
                    $this->security->isGranted(AbstractVoter::OWNER, $subject)
                    && (
                        $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                        || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject->getWorkspace(), $token)
                    )
                )
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow()
            ,

            AssetContainerVoterInterface::ASSET_SHARE => $this->hasAcl([
                PermissionInterface::CHILD_SHARE,
            ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject),

            AssetContainerVoterInterface::ASSET_DELETE => $isCreator()
                || $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow(),

            AssetContainerVoterInterface::ASSET_OWNER => $isCreator()
                || $isWorkspaceOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow(),

            AssetContainerVoterInterface::ASSET_EDIT_PERMISSIONS => $isWorkspaceOwnerFast()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isWorkspaceOwnerSlow(),
            default => false,
        };
    }

    private function parentIsGranted(mixed $attribute, Collection $subject): bool
    {
        return null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent());
    }
}
