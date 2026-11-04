<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Automation\AutomationRun;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * A run is readable by whoever may read its automation.
 */
class AutomationRunVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AutomationRun;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AutomationRun::class, true);
    }

    /**
     * @param AutomationRun $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $automation = $subject->getAutomation();

        return match ($attribute) {
            self::READ => null !== $automation && $this->security->isGranted(self::READ, $automation),
            default => false,
        };
    }
}
