<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Target;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TargetVoter extends Voter
{
    final public const READ = 'READ';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof Target;
    }

    /**
     * @param Target $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if ($this->security->isGranted(JwtUser::ROLE_ADMIN)) {
            return true;
        }

        $user = $token->getUser();
        $groups = [];
        if ($user instanceof JwtUser) {
            $groups = $user->getGroups();
        }

        return match ($attribute) {
            self::READ => empty($subject->getAllowedGroups()) || !empty(array_intersect($groups, $subject->getAllowedGroups())),
            default => false,
        };
    }
}
