<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\UserInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AclVoter extends Voter
{
    private PermissionManager $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    protected function supports($attribute, $subject)
    {
        return is_int($attribute) && $subject instanceof AclObjectInterface;
    }

    /**
     * @param int                $attribute
     * @param AclObjectInterface $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof UserInterface || $user instanceof RemoteUser) {
            return $this->permissionManager->isGranted($user, $subject, $attribute);
        }

        return false;
    }
}
