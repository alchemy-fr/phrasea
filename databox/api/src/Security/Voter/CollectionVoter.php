<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CollectionVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'collection:';

    private array $cache = [];

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
        $key = sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token));

        return $this->cache[$key] ?? ($this->cache[$key] = $this->doVote($attribute, $subject, $token));
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
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $subject->getParent() ? $this->security->isGranted(self::CREATE, $subject->getParent())
                : $this->security->isGranted(WorkspaceVoter::CREATE, $workspace),
            self::LIST => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || ($subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::READ => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT => $isOwner()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::DELETE => $isOwner()
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT_PERMISSIONS, self::OWNER => $isOwner()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::CREATE_ASSET => $isOwner()
                || $this->hasAcl(PermissionInterface::CHILD_CREATE, $subject, $token),
            self::EDIT_ASSET_ATTRIBUTES => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token),
            self::EDIT_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token),
            self::SHARE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                ], $subject, $token),
            self::DELETE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token),
            default => false,
        };
    }
}
