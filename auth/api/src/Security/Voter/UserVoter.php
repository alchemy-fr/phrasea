<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class UserVoter extends Voter
{
    const LIST_USERS = 'LIST_USERS';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $attribute === self::LIST_USERS || $subject instanceof User;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ('READ' === $attribute && $this->security->isGranted('ROLE_USER:READ')) {
            return true;
        }

        if (self::LIST_USERS === $attribute && $this->security->isGranted('ROLE_USER:LIST')) {
            return true;
        }

        if ($token->getUser() instanceof User && $token->getUser() === $subject) {
            return true;
        }

        return false;
    }
}
