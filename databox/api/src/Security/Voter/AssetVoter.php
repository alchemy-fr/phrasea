<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Asset;
    }

    /**
     * @param Asset $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::READ:
                // isGranted VIEW on asset
                // AND validate permissions on tags
                break;
            case self::EDIT:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                    || (
                        null !== $subject->getReferenceCollection()
                        && $this->security->isGranted(PermissionInterface::EDIT, $subject->getReferenceCollection())
                    );
        }

        return false;
    }

}
