<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\TagFilterRule;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TagFilterRuleVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof TagFilterRule;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, TagFilterRule::class, true);
    }

    /**
     * @param TagFilterRule $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $objectClass = TagFilterRule::OBJECT_CLASSES[$subject->getObjectType()];
        $object = $this->em->getRepository($objectClass)->find($subject->getObjectId());

        return $this->security->isGranted(AbstractVoter::EDIT, $object);
    }
}
