<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetVoter extends AbstractVoter
{
    final public const string EDIT_ATTRIBUTES = 'EDIT_ATTRIBUTES';
    final public const string EDIT_TAGS = 'EDIT_TAGS';
    final public const string SHARE = 'SHARE';

    final public const string SCOPE_PREFIX = 'asset:';

    protected function getScopeHierarchy(): array
    {
        return array_merge(parent::getScopeHierarchy(), [
            self::EDIT_ATTRIBUTES => [self::EDIT],
            self::EDIT => [self::OWNER],
            self::EDIT_TAGS => [self::OWNER],
        ]);
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Asset;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Asset::class, true);
    }

    /**
     * @param Asset $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
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
        $isWorkspaceOwnerFast = fn (): bool => $userId && $workspace->getOwnerId() === $userId;
        $isWorkspaceOwnerSlow = fn (): bool => $this->security->isGranted(AbstractVoter::OWNER, $workspace);
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case AbstractVoter::CREATE:
                return $isWorkspaceOwnerFast()
                    || $this->voteOnContainer($subject, AssetContainerVoterInterface::CREATE_ASSET)
                    || $isWorkspaceOwnerSlow();
            case AbstractVoter::READ:
                return (!$subject->isDeleted() || $this->security->isGranted(self::DELETE, $subject))
                    && (
                        $isOwner()
                        || $isWorkspaceOwnerFast()
                        || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                        || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                        || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                        || $this->hasAcl([
                            PermissionInterface::VIEW,
                            PermissionInterface::OWNER,
                        ], $subject, $token)
                        || $this->security->isGranted(AssetContainerVoterInterface::VIEW_ASSET, $workspace)
                        || $this->collectionGrantsAccess($subject)
                        || $isWorkspaceOwnerSlow()
                    )
                ;
            case self::EDIT_ATTRIBUTES:
                return $isOwner()
                    || $isWorkspaceOwnerFast()
                    || $this->hasAcl([
                        PermissionInterface::EDIT,
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->voteOnCollectionOrWorkspace($subject, AssetContainerVoterInterface::EDIT_ASSET_ATTRIBUTES)
                    || $isWorkspaceOwnerSlow();
            case self::EDIT_TAGS:
                return $isWorkspaceOwnerFast()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_TAG, $subject, $token)
                || $this->voteOnCollectionOrWorkspace($subject, $attribute)
                || $isWorkspaceOwnerSlow();
                // Substitute source file, manage its renditions
            case AbstractVoter::EDIT:
                return $isOwner()
                    || $isWorkspaceOwnerFast()
                    || $this->hasAcl([
                        PermissionInterface::OPERATOR,
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->voteOnCollectionOrWorkspace($subject, AssetContainerVoterInterface::EDIT_ASSET)
                    || $isWorkspaceOwnerSlow();
            case self::SHARE:
                return $this->hasAcl(PermissionInterface::SHARE, $subject, $token)
                    || $this->voteOnCollectionOrWorkspace($subject, AssetContainerVoterInterface::SHARE_ASSET)
                    || $isWorkspaceOwnerSlow();
            case AbstractVoter::DELETE:
                return $isOwner()
                    || $isWorkspaceOwnerFast()
                    || $this->hasAcl([
                        PermissionInterface::DELETE,
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->voteOnCollectionOrWorkspace($subject, AssetContainerVoterInterface::DELETE_ASSET)
                    || $isWorkspaceOwnerSlow();
            case AbstractVoter::OWNER:
                return $isOwner()
                    || $isWorkspaceOwnerFast()
                    || $this->hasAcl([
                        PermissionInterface::OWNER,
                    ], $subject, $token)
                    || $this->voteOnCollectionOrWorkspace($subject, $attribute)
                    || $isWorkspaceOwnerSlow();
            case AbstractVoter::EDIT_PERMISSIONS:
                return $isWorkspaceOwnerFast()
                    || $this->security->isGranted(self::OWNER, $subject) && (
                        $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                        || $this->voteOnCollectionOrWorkspace($subject, $attribute)
                    )
                    || $isWorkspaceOwnerSlow()
                ;
        }

        return false;
    }

    private function voteOnContainer(Asset $asset, string|int $attribute): bool
    {
        return $this->security->isGranted($attribute, $asset->getReferenceCollection() ?? $asset->getWorkspace());
    }

    private function voteOnCollectionOrWorkspace(Asset $asset, string|int $attribute): bool
    {
        if ($this->security->isGranted($attribute, $asset->getWorkspace())) {
            return true;
        }

        return $asset->getReferenceCollection() && $this->security->isGranted($attribute, $asset->getReferenceCollection());
    }

    private function collectionGrantsAccess(Asset $subject): bool
    {
        $referenceCollection = $subject->getReferenceCollection();
        if (null !== $referenceCollection && $this->security->isGranted(AssetContainerVoterInterface::VIEW_ASSET, $referenceCollection)) {
            return true;
        }

        foreach ($subject->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            if (null !== $storyAsset = $collection->getStoryAsset()) {
                if ($this->security->isGranted(self::READ, $storyAsset)) {
                    return true;
                }
            } elseif ($this->security->isGranted(AssetContainerVoterInterface::VIEW_ASSET, $collection)) {
                return true;
            }
        }

        return false;
    }
}
