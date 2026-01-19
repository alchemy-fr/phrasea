<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\PublicationProfile;
use App\Security\ScopeInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PublicationProfileVoter extends AbstractVoter
{
    final public const string CREATE_PROFILE = 'profile:create';
    final public const string INDEX_PROFILE = 'profile:index';

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof PublicationProfile || self::CREATE_PROFILE === $attribute;
    }

    /**
     * @param PublicationProfile|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $isAdmin = $this->hasScope(ScopeInterface::SCOPE_PUBLISH, '', false)
            || $this->isAdmin();
        $user = $token->getUser();
        $isAuthenticated = $user instanceof JwtUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        return match ($attribute) {
            self::CREATE_PROFILE => $isAdmin
                || $this->hasAcl(PermissionInterface::CREATE, new PublicationProfile(), $token),
            self::INDEX_PROFILE => $isAuthenticated,
            self::READ => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::DELETE => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::EDIT => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            default => false,
        };
    }
}
