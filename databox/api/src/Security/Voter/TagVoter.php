<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Tag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TagVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Tag;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Tag::class, true);
    }

    /**
     * @param Tag $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace()),
        };
    }
}
