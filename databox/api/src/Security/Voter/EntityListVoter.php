<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\EntityList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EntityListVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'entity-list:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof EntityList;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, EntityList::class, true);
    }

    /**
     * @param EntityList $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());
        $workspaceReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $workspaceEditor(),
            self::READ => $workspaceReader(),
            default => false,
        };
    }
}
