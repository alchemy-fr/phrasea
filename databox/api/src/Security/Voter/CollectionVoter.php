<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Collection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class CollectionVoter extends Voter
{
    const READ = 'READ';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Collection;
    }

    /**
     * @param Collection $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::READ:
                return $subject->isPublic()
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject);
            case self::EDIT:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::DELETE:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject);
        }
    }

}
