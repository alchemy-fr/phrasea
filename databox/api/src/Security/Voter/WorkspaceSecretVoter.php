<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceSecret;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * The secrets and the variables referenced by the configuration of integrations.
 */
class WorkspaceSecretVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof WorkspaceSecret || $subject instanceof WorkspaceEnv;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, WorkspaceSecret::class, true) || is_a($subjectType, WorkspaceEnv::class, true);
    }

    /**
     * @param WorkspaceSecret|WorkspaceEnv $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $workspace = $subject->getWorkspace();

        return match ($attribute) {
            // Even the names are of use to the editors only; the instance secrets are managed by the admins
            self::READ, self::CREATE, self::EDIT, self::DELETE => null !== $workspace
                ? $this->security->isGranted(self::EDIT, $workspace)
                : $this->isAdmin($token),
            default => false,
        };
    }
}
