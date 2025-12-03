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
    final public const string EDIT_RENDITIONS = 'EDIT_RENDITIONS';
    final public const string SHARE = 'SHARE';
    final public const string SCOPE_PREFIX = 'asset:';

    protected function getScopeHierarchy(): array
    {
        return array_merge(parent::getScopeHierarchy(), [
            self::EDIT_ATTRIBUTES => [self::EDIT],
            self::EDIT_RENDITIONS => [self::OWNER],
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

        if (!$this->security->isGranted(self::READ, $subject->getWorkspace(), $token)) {
            return false;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::CREATE:
                if (null !== $collection = $subject->getReferenceCollection()) {
                    return $this->security->isGranted(self::EDIT, $collection, $token);
                }

                return $this->security->isGranted(self::EDIT, $subject->getWorkspace(), $token);
            case self::READ:
                if ($subject->isDeleted()) {
                    return $this->security->isGranted(self::DELETE, $subject, $token);
                }

                return $isOwner()
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                    || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                    || $this->collectionGrantsAccess($subject, $token)
                ;
            case self::EDIT_RENDITIONS:
            case self::EDIT:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                    || $this->voteOnContainer($subject, self::OPERATOR, $token);
            case self::EDIT_ATTRIBUTES:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                    || $this->voteOnContainer($subject, self::EDIT, $token);
            case self::SHARE:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::SHARE, $subject, $token)
                    || $this->voteOnContainer($subject, self::EDIT, $token);
            case self::DELETE:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                    || $this->voteOnContainer($subject, self::DELETE, $token);
            case self::EDIT_PERMISSIONS:
                return $isOwner()
                    || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                    || $this->voteOnContainer($subject, self::OWNER, $token);
        }

        return false;
    }

    private function voteOnContainer(Asset $asset, string|int $attribute, TokenInterface $token): bool
    {
        return $this->security->isGranted($attribute, $asset->getReferenceCollection() ?? $asset->getWorkspace(), $token);
    }

    private function collectionGrantsAccess(Asset $subject, TokenInterface $token): bool
    {
        if (null === $subject->getReferenceCollection() && $this->security->isGranted(self::EDIT, $subject->getWorkspace(), $token)) {
            return true;
        }

        foreach ($subject->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            if (null !== $storyAsset = $collection->getStoryAsset()) {
                if ($this->security->isGranted(self::READ, $storyAsset, $token)) {
                    return true;
                }
            } elseif ($this->security->isGranted(self::READ, $collection, $token)) {
                return true;
            }
        }

        return false;
    }
}
