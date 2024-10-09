<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Share;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ShareVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Share;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Share::class, true);
    }

    /**
     * @param Share $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated() && $this->security->isGranted(AssetVoter::SHARE, $subject->getAsset()),
            self::READ, self::EDIT, self::DELETE => $isOwner()
                || $this->security->isGranted(AssetVoter::SHARE, $subject->getAsset()),
            default => false,
        };
    }
}
