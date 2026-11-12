<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Profile\Profile;
use App\Security\InstancePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class ProfileVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'profile:';

    /**
     * Make the profile public, or grant others access to it.
     */
    final public const string SHARE = 'SHARE';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Profile;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Profile::class, true);
    }

    /**
     * @param Profile $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated(),
            self::READ => $subject->isPublic()
                || $isOwner()
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::EDIT => $isOwner()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isOwner()
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            // Sharing its own profiles is an instance capability
            self::SHARE, self::EDIT_PERMISSIONS => ($isOwner() && $this->security->isGranted(InstancePermission::SHARE_PROFILE))
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token, ownershipGrants: false),
            default => false,
        };
    }
}
