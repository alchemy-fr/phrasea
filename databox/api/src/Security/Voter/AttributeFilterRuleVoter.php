<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeFilterRule;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AttributeFilterRuleVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeFilterRule;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeFilterRule::class, true);
    }

    /**
     * @param AttributeFilterRule $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->security->isGranted(self::EDIT, $subject->getWorkspace());
    }
}
