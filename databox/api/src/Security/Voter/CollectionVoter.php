<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CollectionVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'collection:';
    }
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
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        $workspace = $subject->getWorkspace();

        return match ($attribute) {
            self::CREATE => $this->hasScope($token, $attribute)
                || ($subject->getParent() ? $this->security->isGranted(AbstractVoter::EDIT, $subject->getParent())
                : $this->security->isGranted(AbstractVoter::EDIT, $workspace)),
            self::LIST => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || $this->hasScope($token, self::READ)
                || ($this->security->isGranted(AbstractVoter::READ, $workspace) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::READ => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || $this->hasScope($token, $attribute)
                || ($this->security->isGranted(AbstractVoter::READ, $workspace) && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                || ($subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::DELETE => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::EDIT_PERMISSIONS, self::OWNER => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            self::OPERATOR => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token)
                || (null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent())),
            default => false,
        };
    }
}
