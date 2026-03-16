<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\RenditionDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionDefinitionVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';
    private const string SCOPE_PREFIX = 'rendition-definition:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof RenditionDefinition;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, RenditionDefinition::class, true);
    }

    /**
     * @param RenditionDefinition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $isWorkspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT => $isWorkspaceEditor() || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX),
            self::READ_ADMIN => $isWorkspaceEditor()
                || $this->tokenHasScope($token, self::READ, self::SCOPE_PREFIX),
            self::READ => true,
            default => false,
        };
    }
}
