<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Workflow\WorkflowState;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkflowStateVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof WorkflowState;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, WorkflowState::class, true);
    }

    /**
     * @param WorkflowState $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (null !== $asset = $subject->getAsset()) {
            return $this->security->isGranted(AbstractVoter::EDIT, $asset);
        }

        return false;
    }
}
