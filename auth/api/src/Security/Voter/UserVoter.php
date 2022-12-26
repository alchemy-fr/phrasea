<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class UserVoter extends Voter
{
    const READ = 'READ';
    const LIST_USERS = 'LIST_USERS';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return self::LIST_USERS === $attribute || $subject instanceof User;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::READ:
                return $this->security->isGranted('ROLE_USER')
                    || $this->security->isGranted('ROLE_USER:READ')
                    || $token->getUser() instanceof User && $token->getUser() === $subject;
            case self::LIST_USERS:
                return $this->security->isGranted('ROLE_USER')
                    || $this->security->isGranted('ROLE_USER:LIST') // Scope
                    || $this->security->isGranted('ROLE_ADMIN_USERS')
                ;
            default:
                return false;
        }
    }
}
