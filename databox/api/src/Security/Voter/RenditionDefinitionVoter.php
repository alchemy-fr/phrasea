<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\RenditionDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionDefinitionVoter extends AbstractVoter
{
    final public const READ_ADMIN = 'READ_ADMIN';
    final public const SCOPE_PREFIX = 'ROLE_RENDITION-DEFINITION:';

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof RenditionDefinition;
    }

    /**
     * @param RenditionDefinition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $workspaceEditor = $this->security->isGranted(WorkspaceVoter::EDIT, $subject->getWorkspace());
        return match ($attribute) {
            self::CREATE => $workspaceEditor || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::EDIT => $workspaceEditor || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT'),
            self::DELETE => $workspaceEditor || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE'),
            self::READ_ADMIN => $workspaceEditor
                || $this->security->isGranted(self::SCOPE_PREFIX.'READ'),
            self::READ => true,
            default => false,
        };
    }
}
