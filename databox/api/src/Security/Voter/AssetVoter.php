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
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::CREATE:
                if (null !== $collection = $subject->getReferenceCollection()) {
                    return $this->security->isGranted(AbstractVoter::EDIT, $collection);
                }

                return $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());
            case self::READ:
                return $isOwner()
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || $this->hasScope($token, $attribute)
                    || ($this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace()) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                    || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                    || $this->collectionGrantsAccess($subject)
                ;
            case self::EDIT_RENDITIONS:
            case self::EDIT:
                return $isOwner()
                    || $this->hasScope($token, $attribute)
                    || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                    || $this->voteOnContainer($subject, AbstractVoter::OPERATOR);
            case self::EDIT_ATTRIBUTES:
                return $isOwner()
                    || $this->hasScope($token, $attribute)
                    || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                    || $this->voteOnContainer($subject, AbstractVoter::EDIT);
            case self::SHARE:
                return $isOwner()
                    || $this->hasScope($token, $attribute)
                    || $this->hasAcl(PermissionInterface::SHARE, $subject, $token)
                    || $this->voteOnContainer($subject, AbstractVoter::EDIT);
            case self::DELETE:
                return $isOwner()
                    || $this->hasScope($token, $attribute)
                    || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                    || $this->voteOnContainer($subject, AbstractVoter::DELETE);
            case self::EDIT_PERMISSIONS:
                return $isOwner()
                    || $this->hasScope($token, $attribute)
                    || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                    || $this->voteOnContainer($subject, AbstractVoter::OWNER);
        }

        return false;
    }

    private function voteOnContainer(Asset $asset, string|int $attribute): bool
    {
        return $this->security->isGranted($attribute, $asset->getReferenceCollection() ?? $asset->getWorkspace());
    }

    private function collectionGrantsAccess(Asset $subject): bool
    {
        if (null === $subject->getReferenceCollection() && $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace())) {
            return true;
        }

        foreach ($subject->getCollections() as $collectionAsset) {
            if ($this->security->isGranted(AbstractVoter::READ, $collectionAsset->getCollection())) {
                return true;
            }
        }

        return false;
    }

    public static function getScopePrefix(): string
    {
        return 'asset:';
    }
}
