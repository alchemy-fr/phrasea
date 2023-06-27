<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class UserVoter extends Voter
{
    final public const READ = 'READ';
    final public const LIST_USERS = 'LIST_USERS';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return self::LIST_USERS === $attribute || $subject instanceof User;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted('ROLE_USER')
                || $this->security->isGranted('ROLE_USER:READ')
                || $token->getUser() instanceof User && $token->getUser() === $subject,
            self::LIST_USERS => $this->security->isGranted('ROLE_USER')
                || $this->security->isGranted('ROLE_USER:LIST') // Scope
                || $this->security->isGranted('ROLE_ADMIN_USERS'),
            default => false,
        };
    }
}
