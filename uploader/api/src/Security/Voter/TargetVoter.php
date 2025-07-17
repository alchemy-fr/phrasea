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
    final public const string READ = 'READ';
    final public const string UPLOAD = 'UPLOAD';

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

        $isAllowed = fn () => empty($subject->getAllowedGroups()) || !empty(array_intersect($groups, $subject->getAllowedGroups()));

        return match ($attribute) {
            self::UPLOAD => $subject->isEnabled() && $isAllowed(),
            self::READ => $isAllowed(),
            default => false,
        };
    }
}
