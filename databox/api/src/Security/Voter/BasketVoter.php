<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Basket\Basket;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class BasketVoter extends AbstractVoter
{
    final public const SCOPE_PREFIX = 'ROLE_BASKET:';
    final public const SHARE = 'SHARE';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Basket;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Basket::class, true);
    }

    /**
     * @param Basket $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn(): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated(),
            self::READ => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'READ')
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::EDIT, self::SHARE => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                || $this->security->isGranted(self::SCOPE_PREFIX.'SHARE')
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE')
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::EDIT_PERMISSIONS => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'OWNER')
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            default => false,
        };
    }
}
