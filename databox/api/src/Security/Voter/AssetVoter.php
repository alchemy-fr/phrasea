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
    final public const SCOPE_PREFIX = 'ROLE_ASSET:';
    final public const EDIT_ATTRIBUTES = 'EDIT_ATTRIBUTES';
    final public const EDIT_RENDITIONS = 'EDIT_RENDITIONS';
    final public const SHARE = 'SHARE';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Asset;
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
                    || $this->security->isGranted(self::SCOPE_PREFIX.'READ')
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || ($this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace()) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                    || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                    || $this->collectionGrantsAccess($subject)
                ;
            case self::EDIT_RENDITIONS:
            case self::EDIT:
                return $isOwner()
                    || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                    || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->hasAcl(PermissionInterface::OPERATOR, $subject->getReferenceCollection(), $token)
                    );
            case self::EDIT_ATTRIBUTES:
                return $isOwner()
                    || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                    || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->hasAcl(PermissionInterface::EDIT, $subject->getReferenceCollection(), $token)
                    );
            case self::SHARE:
                return $isOwner()
                    || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                    || $this->hasAcl(PermissionInterface::SHARE, $subject, $token)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->hasAcl(PermissionInterface::EDIT, $subject->getReferenceCollection(), $token)
                    );
            case self::DELETE:
                return $isOwner()
                    || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE')
                    || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->hasAcl(PermissionInterface::DELETE, $subject->getReferenceCollection(), $token)
                    );
            case self::EDIT_PERMISSIONS:
                return $isOwner()
                    || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                    || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->hasAcl(PermissionInterface::OWNER, $subject->getReferenceCollection(), $token)
                    );
        }

        return false;
    }

    private function collectionGrantsAccess(Asset $subject): bool
    {
        foreach ($subject->getCollections() as $collectionAsset) {
            if ($this->security->isGranted(AbstractVoter::READ, $collectionAsset->getCollection())) {
                return true;
            }
        }

        return false;
    }
}
