<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\AttributeList\AttributeList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeListVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'attribute-list:';
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeList;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeList::class, true);
    }

    /**
     * @param AttributeList $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated(),
            self::READ => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::EDIT => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::EDIT_PERMISSIONS => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            default => false,
        };
    }
}
