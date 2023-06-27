<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Group;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class GroupVoter extends Voter
{
    public const LIST_GROUPS = 'LIST_GROUPS';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool
    {
        return self::LIST_GROUPS === $attribute || $subject instanceof Group;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        switch ($attribute) {
            case self::LIST_GROUPS:
                return $this->security->isGranted('ROLE_USER')
                    || $this->security->isGranted('ROLE_GROUP:LIST') // Scope
                    || $this->security->isGranted('ROLE_ADMIN_USERS')
                ;
            default:
                return false;
        }
    }
}
