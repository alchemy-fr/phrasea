<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceIntegrationVoter extends AbstractVoter
{
    final public const string SCOPE_PREFIX = 'ROLE_INTEGRATION:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof WorkspaceIntegration;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, WorkspaceIntegration::class, true);
    }

    /**
     * @param WorkspaceIntegration $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::EDIT => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT'),
            self::DELETE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE'),
            self::READ => true,
            default => false,
        };
    }
}
