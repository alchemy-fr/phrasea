<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\RenditionDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionDefinitionVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';
    final public const string SCOPE_PREFIX = 'ROLE_RENDITION-DEFINITION:';

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
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::EDIT => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT'),
            self::DELETE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE'),
            self::READ_ADMIN => $workspaceEditor()
                || $this->security->isGranted(self::SCOPE_PREFIX.'READ'),
            self::READ => true,
            default => false,
        };
    }
}
