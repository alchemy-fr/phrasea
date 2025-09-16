<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\EntityList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EntityListVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'entity-list:';
    }

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
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());
        $workspaceReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $workspaceEditor() || $this->tokenHasScope($token, $attribute),
            self::EDIT => $workspaceEditor() || $this->tokenHasScope($token, $attribute),
            self::DELETE => $workspaceEditor() || $this->tokenHasScope($token, $attribute),
            self::READ => $workspaceReader() || $this->tokenHasScope($token, $attribute),
            default => false,
        };
    }
}
