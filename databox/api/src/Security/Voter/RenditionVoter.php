<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\AssetRendition;
use App\Security\RenditionPermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionVoter extends AbstractVoter
{
    public function __construct(private readonly RenditionPermissionManager $renditionPermissionManager)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetRendition;
    }

    /**
     * @param AssetRendition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = null;
        $groupIds = [];
        if ($user instanceof JwtUser) {
            $userId = $user->getId();
            $groupIds = $user->getGroupIds();
        }

        return match ($attribute) {
            self::READ => $this->renditionPermissionManager->isGranted(
                $subject->getAsset(),
                $subject->getDefinition()->getClass(),
                $userId,
                $groupIds
            ),
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(AssetVoter::EDIT_RENDITIONS, $subject->getAsset()),
            default => false,
        };
    }
}
