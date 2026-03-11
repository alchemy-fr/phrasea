<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CollectionVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'collection:';

    private CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

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
        return $this->cache->get(sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token)), function () use ($attribute, $subject, $token) {
            return $this->doVote($attribute, $subject, $token);
        });
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
        $isOwnerOfParent = fn (): bool => $userId && $this->isOwnerOfCollection($subject, $userId, $token);

        return match ($attribute) {
            self::CREATE => ($subject->getParent() ? $this->security->isGranted(self::CREATE, $subject->getParent())
                : $this->security->isGranted(WorkspaceVoter::CREATE, $workspace)
                || $isOwnerOfParent()
            ),
            self::LIST => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || ($subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::READ => $isOwner()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::EDIT => $isOwner()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::DELETE => $isOwner()
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::EDIT_PERMISSIONS, self::OWNER => $isOwner()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::CREATE_ASSET => $isOwner()
                || $this->hasAcl(PermissionInterface::CHILD_CREATE, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::EDIT_ASSET_ATTRIBUTES => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerOfParent()
            ,
            self::EDIT_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $isOwnerOfParent(),
            self::SHARE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $isOwnerOfParent(),
            self::DELETE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $isOwnerOfParent(),
            default => false,
        };
    }

    private function parentIsGranted(mixed $attribute, Collection $subject): bool
    {
        return null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent());
    }

    private function isOwnerOfCollection(Collection $subject, string $userId, TokenInterface $token): bool
    {
        return $this->collectionHierarchyHasPermissions([PermissionInterface::OWNER], $subject, $userId, $token);
    }

    private function collectionHierarchyHasPermissions(array $permissions, Collection $subject, string $userId, TokenInterface $token, bool $firstCall = true): bool
    {
        if (
            $subject->getOwnerId() === $userId
            || ($firstCall && $subject->getWorkspace()->getOwnerId() === $userId)
            || $this->hasAcl($permissions, $subject, $token)
            || ($firstCall && $this->hasAcl($permissions, $subject->getWorkspace(), $token))
        ) {
            return true;
        }

        if ($subject->getParent()) {
            return $this->collectionHierarchyHasPermissions($permissions, $subject->getParent(), $userId, $token, false);
        }

        return false;
    }
}
