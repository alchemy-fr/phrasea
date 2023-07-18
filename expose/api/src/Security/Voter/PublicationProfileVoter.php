<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoter;
use App\Entity\PublicationProfile;
use App\Security\ScopeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PublicationProfileVoter extends Voter
{
    final public const CREATE = 'profile:create';
    final public const INDEX = 'profile:index';
    final public const READ = 'READ';
    final public const EDIT = 'EDIT';
    final public const DELETE = 'DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof PublicationProfile || self::CREATE === $attribute;
    }

    /**
     * @param PublicationProfile|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $isAdmin = $this->security->isGranted(ScopeVoter::PREFIX.ScopeInterface::SCOPE_PUBLISH) || $this->security->isGranted(JwtUser::ROLE_ADMIN);
        $user = $token->getUser();
        $isAuthenticated = $user instanceof JwtUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        return match ($attribute) {
            self::CREATE => $isAdmin
                || $this->security->isGranted(PermissionInterface::CREATE, new PublicationProfile()),
            self::INDEX => $isAuthenticated,
            self::READ => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::VIEW, $subject),
            self::DELETE => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::DELETE, $subject),
            self::EDIT => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            default => false,
        };
    }
}
