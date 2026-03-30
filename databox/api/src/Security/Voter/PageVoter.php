<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Page\Page;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PageVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'page:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Page;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Page::class, true);
    }

    /**
     * @param Page $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->hasAcl(PermissionInterface::CREATE, $subject, $token),
            self::READ => ($subject->isPublic() && $subject->isEnabled())
                || ($this->hasAcl(PermissionInterface::VIEW, $subject, $token) && $subject->isEnabled())
                || $isOwner(),
            self::EDIT => $isOwner()
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isOwner()
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::EDIT_PERMISSIONS => $isOwner()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            default => false,
        };
    }
}
