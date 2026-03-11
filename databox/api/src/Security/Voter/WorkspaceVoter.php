<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class WorkspaceVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'workspace:';

    private CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace && !is_numeric($attribute);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return !is_numeric($attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Workspace::class, true);
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->cache->get(sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token)), function () use ($attribute, $subject, $token) {
            return $this->doVote($attribute, $subject, $token);
        });
    }

    private function doVote(string $attribute, Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        return match ($attribute) {
            self::CREATE => $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->isAdmin()
        || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            self::READ => $isOwner()
                || $subject->isPublic()
                || $this->hasAcl([
                    PermissionInterface::VIEW,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin()
            ,
            self::EDIT => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::EDIT,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            self::DELETE => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->isAdmin(),
            self::EDIT_PERMISSIONS, self::OWNER => $isOwner()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->isAdmin(),
            self::CREATE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_CREATE,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            self::SHARE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            self::EDIT_ASSET_ATTRIBUTES => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            self::EDIT_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            self::DELETE_ASSET => $isOwner()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_MASTER,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),

            default => false,
        };
    }
}
