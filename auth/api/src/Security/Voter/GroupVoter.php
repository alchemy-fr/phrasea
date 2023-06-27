<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Group;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class GroupVoter extends Voter
{
    final public const LIST_GROUPS = 'LIST_GROUPS';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return self::LIST_GROUPS === $attribute || $subject instanceof Group;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::LIST_GROUPS => $this->security->isGranted('ROLE_USER')
                || $this->security->isGranted('ROLE_GROUP:LIST') // Scope
                || $this->security->isGranted('ROLE_ADMIN_USERS'),
            default => false,
        };
    }
}
