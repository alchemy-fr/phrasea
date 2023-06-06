<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CollectionVoter extends AbstractVoter
{
    private array $cache = [];

    protected function supports(string $attribute, $subject):bool
    {
        return $subject instanceof Collection;
    }

    /**
     * @param Collection $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token):bool
    {
        $key = sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token));
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->doVote($attribute, $subject, $token);
    }

    private function doVote(string $attribute, Collection $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $subject->getParent() ? $this->security->isGranted(CollectionVoter::EDIT, $subject->getParent())
                : $this->security->isGranted(WorkspaceVoter::EDIT, $subject->getWorkspace()),
            self::LIST => $isOwner
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || ($this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace()) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->security->isGranted(PermissionInterface::VIEW, $subject)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::READ => $isOwner
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || ($this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace()) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                || $this->security->isGranted(PermissionInterface::VIEW, $subject)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT => $isOwner
                || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::DELETE => $isOwner
                || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT_PERMISSIONS => $isOwner
                || $this->security->isGranted(PermissionInterface::OWNER, $subject)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            default => false,
        };
    }
}
