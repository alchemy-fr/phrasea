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

    /**
     * @param AssetFileVersion $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(AssetVoter::READ, $subject->getAsset()),
            self::DELETE => $this->security->isGranted(AssetVoter::DELETE, $subject->getAsset()),
            default => false,
        };
    }
}
