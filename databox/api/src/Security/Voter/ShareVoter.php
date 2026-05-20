<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Share;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ShareVoter extends AbstractVoter
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

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

        $asset = $subject->getAsset();

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated()
                && $this->security->isGranted(AssetVoter::READ, $asset)
                && $this->security->isGranted(AssetVoter::SHARE, $asset)
            ,
            self::READ => $isOwner()
                || $this->security->isGranted(AssetVoter::SHARE, $asset)
                || $this->hasValidToken($subject),
            self::EDIT,
            self::DELETE => $isOwner()
                || $this->security->isGranted(AssetVoter::SHARE, $asset),
            default => false,
        };
    }

    private function hasValidToken(Share $share): bool
    {
        if (
            !$share->isEnabled()
            || ($share->getExpiresAt() && $share->getExpiresAt() < new \DateTimeImmutable())
            || ($share->getStartsAt() && $share->getStartsAt() > new \DateTimeImmutable())
        ) {
            return false;
        }

        $token = $this->requestStack->getCurrentRequest()?->get('token');

        return $token && $token === $share->getToken();
    }
}
