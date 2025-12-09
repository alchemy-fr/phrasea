<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\CollectionAsset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CollectionAssetVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof CollectionAsset;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, CollectionAsset::class, true);
    }

    /**
     * @param CollectionAsset $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::CREATE => $this->security->isGranted(AbstractVoter::EDIT, $subject->getCollection())
                && $this->security->isGranted(AbstractVoter::EDIT, $subject->getAsset()),
            self::DELETE => $this->security->isGranted(AbstractVoter::EDIT, $subject->getCollection()),
            default => false,
        };
    }
}
