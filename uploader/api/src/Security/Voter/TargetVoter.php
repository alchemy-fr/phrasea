<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Target;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class TargetVoter extends Voter
{
    const READ = 'READ';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Target;
    }

    /**
     * @param Target      $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        $groups = [];
        if ($user instanceof RemoteUser) {
            $groups = $user->getGroupIds();
        }

        switch ($attribute) {
            case self::READ:
                return empty($subject->getAllowedGroups()) || !empty(array_intersect($groups, $subject->getAllowedGroups()));
            default:
                return false;
        }
    }
}
