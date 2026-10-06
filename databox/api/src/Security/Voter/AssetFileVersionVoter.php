<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetFileVersion;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AssetFileVersionVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetFileVersion;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetFileVersion::class, true);
    }

    /**
     * @param AssetFileVersion $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(self::READ, $subject->getAsset()),
            self::DELETE => $this->security->isGranted(self::DELETE, $subject->getAsset()),
            default => false,
        };
    }
}
