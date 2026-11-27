<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Share;
use App\Model\ShareTokenAccessEnum;
use App\Service\Share\ShareAccessManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class ShareVoter extends AbstractVoter
{
    public function __construct(
        private readonly ShareAccessManager $shareAccessManager,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Share;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Share::class, true);
    }

    /**
     * @param Share $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        $assets = $subject->getAssetsList();

        $canShareAllAssets = function () use ($assets): bool {
            if (empty($assets)) {
                return false;
            }

            foreach ($assets as $asset) {
                if (!$this->security->isGranted(AssetVoter::SHARE, $asset)) {
                    return false;
                }
            }

            return true;
        };

        $canReadAllAssets = function () use ($assets): bool {
            foreach ($assets as $asset) {
                if (!$this->security->isGranted(AssetVoter::READ, $asset)) {
                    return false;
                }
            }

            return true;
        };

        return match ($attribute) {
            self::CREATE => $this->isAuthenticated()
                && $canReadAllAssets()
                && $canShareAllAssets()
            ,
            // The owner of a share loses it once an attribute filter rule hides one of its assets
            self::READ => ($isOwner() && $canReadAllAssets())
                || $this->hasValidToken($subject)
                || $canShareAllAssets(),
            self::EDIT => ($isOwner() && $canReadAllAssets())
                || $canShareAllAssets(),
            self::DELETE => $isOwner()
                || $canShareAllAssets(),
            default => false,
        };
    }

    /**
     * The token of the link, and the access token handed out once the password is given for
     * a protected share.
     */
    private function hasValidToken(Share $share): bool
    {
        return ShareTokenAccessEnum::Granted === $this->shareAccessManager->resolveTokenAccess($share);
    }
}
