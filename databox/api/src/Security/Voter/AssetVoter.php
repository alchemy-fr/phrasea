<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Asset;
    }

    /**
     * @param Asset $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        $workspaceIds = $userId ? $this->getAllowedWorkspaceIds($userId, $user->getGroupIds()) : [];

        switch ($attribute) {
            case self::CREATE:
                if (null !== $collection = $subject->getReferenceCollection()) {
                    return $this->security->isGranted(CollectionVoter::EDIT, $collection);
                }
                if ($subject->getWorkspace()) {
                    return $this->security->isGranted(WorkspaceVoter::EDIT, $subject->getWorkspace());
                }

                return $user instanceof RemoteUser;
            case self::READ:
                return $isOwner
                    || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || (in_array($subject->getWorkspaceId(), $workspaceIds, true) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject)
                    || $this->collectionGrantsAccess($subject)
                    ;
            case self::EDIT:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->security->isGranted(PermissionInterface::EDIT, $subject->getReferenceCollection())
                    );
            case self::DELETE:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->security->isGranted(PermissionInterface::DELETE, $subject->getReferenceCollection())
                    );
            case self::EDIT_PERMISSIONS:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::OWNER, $subject)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->security->isGranted(PermissionInterface::OWNER, $subject->getReferenceCollection())
                    );
        }

        return false;
    }

    private function collectionGrantsAccess(Asset $subject): bool
    {
        foreach ($subject->getCollections() as $collectionAsset) {
            if ($this->security->isGranted(CollectionVoter::READ, $collectionAsset->getCollection())) {
                return true;
            }
        }

        return false;
    }
}
