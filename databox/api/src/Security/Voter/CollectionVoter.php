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
        $isOwnerFast = fn (): bool => $userId && $subject->getOwnerId() === $userId || $subject->getWorkspace()->getOwnerId() === $userId;
        $isOwnerSlow = fn (): bool => $this->hasAcl(PermissionInterface::OWNER, $subject, $token) || $this->security->isGranted(self::OWNER, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $isOwnerFast()
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::LIST => $isOwnerFast()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE)
                || ($subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::READ => $isOwnerFast()
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC
                || ($userId && $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS)
                || $subject->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT => $isOwnerFast()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::DELETE => $isOwnerFast()
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT_PERMISSIONS, self::OWNER => $isOwnerFast()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::CREATE_ASSET => $isOwnerFast()
                || $this->hasAcl(PermissionInterface::CHILD_CREATE, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT_ASSET_ATTRIBUTES => $isOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow()
            ,
            self::EDIT_ASSET => $isOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            self::EDIT_ASSET_PRIVACY => $isOwnerFast()
                || $this->hasMetadata(AssetContainerVoterInterface::PERM_EDIT_PRIVACY, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            self::EDIT_ASSET_TAG => ($userId && $subject->getWorkspace()->getOwnerId() === $userId)
                || $this->hasMetadata(AssetContainerVoterInterface::PERM_EDIT_TAG, $subject, $token)
                || $this->parentIsGranted($attribute, $subject),
            self::EDIT_COLLECTION_PRIVACY => ($userId && $subject->getWorkspace()->getOwnerId() === $userId)
                || $this->hasMetadata(AssetContainerVoterInterface::PERM_EDIT_PRIVACY, $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $this->hasAcl(PermissionInterface::OWNER, $subject->getWorkspace(), $token)
            ,
            self::SHARE_ASSET => $isOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            self::DELETE_ASSET => $isOwnerFast()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                ], $subject, $token)
                || $this->parentIsGranted($attribute, $subject)
                || $isOwnerSlow(),
            default => false,
        };
    }

    private function parentIsGranted(mixed $attribute, Collection $subject): bool
    {
        return null !== $subject->getParent() && $this->security->isGranted($attribute, $subject->getParent());
    }
}
