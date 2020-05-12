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
    const CREATE = 'profile:create';
    const INDEX = 'profile:index';
    const READ = 'READ';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof PublicationProfile || self::CREATE === $attribute;
    }

    /**
     * @param PublicationProfile|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $isAdmin = $this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN');
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;

        switch ($attribute) {
            case self::CREATE:
                return $isAdmin;
            case self::INDEX:
                return $isAdmin;
            case self::READ:
                return $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId())
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject);
            case self::DELETE:
                return $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId())
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                    ;
            case self::EDIT:
                return $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId())
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                    ;
            default:
                return false;
        }
    }
}
