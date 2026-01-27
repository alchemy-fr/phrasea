<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\SubDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SubDefinitionVoter extends AbstractVoter
{
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, SubDefinition::class, true);
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof SubDefinition;
    }

    /**
     * @param SubDefinition $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(AbstractVoter::READ, $subject->getAsset()),
            self::CREATE, self::DELETE, self::EDIT => $this->security->isGranted(AbstractVoter::EDIT, $subject->getAsset()),
            default => false,
        };
    }
}
