<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Basket\Basket;
use App\Security\InstancePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class BasketVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'basket:';

    final public const string SHARE = 'SHARE';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Basket;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Basket::class, true);
    }

    /**
     * @param Basket $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated(),
            self::READ => $isOwner()
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::EDIT => $isOwner()
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            // Sharing its own baskets is an instance capability
            self::SHARE => ($isOwner() && $this->security->isGranted(InstancePermission::SHARE_BASKET))
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)
                || $this->hasAcl(PermissionInterface::SHARE, $subject, $token, ownershipGrants: false)
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token, ownershipGrants: false),
            self::DELETE => $isOwner()
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::EDIT_PERMISSIONS => ($isOwner() && $this->security->isGranted(InstancePermission::SHARE_BASKET))
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token, ownershipGrants: false),
            default => false,
        };
    }
}
