<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class CollectionVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Collection;
    }

    /**
     * @param Collection $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        $workspaceIds = $userId ? $this->getAllowedWorkspaceIds($userId, $user->getGroupIds()) : [];

        switch ($attribute) {
            case self::LIST:
                return $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                    || (in_array($subject->getWorkspaceId(), $workspaceIds, true) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject)
                    || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent()));
            case self::READ:
                return $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                    || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                    || (in_array($subject->getWorkspaceId(), $workspaceIds, true) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject)
                    || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent()));
            case self::EDIT:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                    || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent()));
            case self::DELETE:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                    || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent()));
            case self::EDIT_PERMISSIONS:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::OWNER, $subject)
                    || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent()));
        }
    }

}
