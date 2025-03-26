<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @deprecated User SecurityAwareTrait instead
 */
final class ScopeVoter extends Voter
{
    use ScopeVoterTrait;

    final public const string PREFIX = 'scope:';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null === $subject && str_starts_with($attribute, self::PREFIX);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $this->hasScope(substr($attribute, strlen(self::PREFIX)), $token);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, self::PREFIX);
    }

    public function supportsType(string $subjectType): bool
    {
        return 'null' === $subjectType;
    }
}
