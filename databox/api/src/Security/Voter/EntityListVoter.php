<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\EntityList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class EntityListVoter extends AbstractVoter
{
    final public const string SCOPE_PREFIX = 'entity-list:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof EntityList;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, EntityList::class, true);
    }

    /**
     * @param EntityList $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $isWorkspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());
        $isWorkspaceReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $isWorkspaceEditor(),
            self::READ => $isWorkspaceReader(),
            default => false,
        };
    }
}
