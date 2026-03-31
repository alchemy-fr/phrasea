<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceIntegrationVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'integration:';
    final public const string READ_DATA = 'READ_DATA';
    final public const string INTERACT = 'INTERACT';

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
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $isWorkspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());
        $isWorkspaceReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $isWorkspaceEditor(),
            self::EDIT_PERMISSIONS => $isWorkspaceEditor() || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            self::EDIT => $isWorkspaceEditor() || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isWorkspaceEditor() || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::READ => $isWorkspaceReader()
        || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::READ_DATA => $this->isAuthenticated()
                && (true === $subject->getPublic() || $this->hasAcl(PermissionInterface::CHILD_VIEW, $subject, $token)),
            self::INTERACT => $this->isAuthenticated() && $this->hasAcl(PermissionInterface::CHILD_EDIT, $subject, $token),
            default => false,
        };
    }
}
