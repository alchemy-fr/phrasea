<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetFileVersion;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetFileVersionVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetFileVersion;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetFileVersion::class, true);
    }

    /**
     * @param AssetFileVersion $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(AbstractVoter::READ, $subject->getAsset()),
            self::DELETE => $this->security->isGranted(AbstractVoter::DELETE, $subject->getAsset()),
            default => false,
        };
    }
}
