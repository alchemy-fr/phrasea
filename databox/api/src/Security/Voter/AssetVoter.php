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
    final public const string EDIT_PRIVACY = 'EDIT_PRIVACY';
    final public const string SHARE = 'SHARE';

    final public const string SCOPE_PREFIX = 'asset:';

    protected function getScopeHierarchy(): array
    {
        return array_merge(parent::getScopeHierarchy(), [
            self::EDIT_ATTRIBUTES => [self::EDIT],
            self::EDIT => [self::OWNER],
            self::EDIT_PRIVACY => [self::OWNER],
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

        if (!$this->security->isGranted(self::READ, $subject->getWorkspace())) {
            return false;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isWorkspaceOwnerFast = fn (): bool => $userId && $subject->getWorkspace()->getOwnerId() === $userId;
        $isWorkspaceOwnerSlow = fn (): bool => $this->hasAcl(PermissionInterface::OWNER, $subject->getWorkspace(), $token);
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::CREATE:
                return $this->voteOnContainer($subject, AssetContainerVoterInterface::CREATE_ASSET);
            case self::READ:
                if ($subject->isDeleted()) {
                    return $this->security->isGranted(self::DELETE, $subject);
                }

                return $isOwner()
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                    || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                    || $this->collectionGrantsAccess($subject)
                ;
            case self::EDIT_ATTRIBUTES:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                    || $this->voteOnContainer($subject, AssetContainerVoterInterface::EDIT_ASSET_ATTRIBUTES);
            case self::EDIT_TAGS:
                return $isWorkspaceOwnerFast()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_TAG, $subject, $token)
                || $this->voteOnContainer($subject, $attribute)
                || $isWorkspaceOwnerSlow();
                // Substitute source file, manage its renditions
            case self::EDIT:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                    || $this->voteOnContainer($subject, AssetContainerVoterInterface::EDIT_ASSET);
            case self::SHARE:
                return $this->hasAcl(PermissionInterface::SHARE, $subject, $token)
                    || $this->voteOnContainer($subject, AssetContainerVoterInterface::SHARE_ASSET);
            case self::DELETE:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                    || $this->voteOnContainer($subject, AssetContainerVoterInterface::DELETE_ASSET);
            case self::OWNER:
            case self::EDIT_PERMISSIONS:
            case self::EDIT_PRIVACY:
                return $isWorkspaceOwnerFast()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                || $this->voteOnContainer($subject, $attribute)
                || $isWorkspaceOwnerSlow();
        }

        return false;
    }

    private function voteOnContainer(Asset $asset, string|int $attribute): bool
    {
        return $this->security->isGranted($attribute, $asset->getReferenceCollection() ?? $asset->getWorkspace());
    }

    private function collectionGrantsAccess(Asset $subject): bool
    {
        if (null === $subject->getReferenceCollection() && $this->security->isGranted(self::EDIT, $subject->getWorkspace())) {
            return true;
        }

        foreach ($subject->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            if (null !== $storyAsset = $collection->getStoryAsset()) {
                if ($this->security->isGranted(self::READ, $storyAsset)) {
                    return true;
                }
            } elseif ($this->security->isGranted(self::READ, $collection)) {
                return true;
            }
        }

        return false;
    }
}
