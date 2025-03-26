<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceIntegrationVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
{
    return 'integration:';
}

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
        $workspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());
        $workspaceReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::READ => $workspaceReader(),
            default => false,
        };
    }
}
