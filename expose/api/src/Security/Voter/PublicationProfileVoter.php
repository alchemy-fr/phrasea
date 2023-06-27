<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\PublicationProfile;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class PublicationProfileVoter extends Voter
{
    public const CREATE = 'profile:create';
    public const INDEX = 'profile:index';
    public const READ = 'READ';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
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
        $isAdmin = $this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN');
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        switch ($attribute) {
            case self::CREATE:
                return $isAdmin
                    || $this->security->isGranted(PermissionInterface::CREATE, new PublicationProfile());
            case self::INDEX:
                return $isAuthenticated;
            case self::READ:
                return $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject);
            case self::DELETE:
                return $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                ;
            case self::EDIT:
                return $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                ;
            default:
                return false;
        }
    }
}
