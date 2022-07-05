<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\AssetRendition;
use App\Security\RenditionPermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionVoter extends AbstractVoter
{
    const SCOPE_PREFIX = 'ROLE_RENDITION:';
    private RenditionPermissionManager $renditionPermissionManager;

    public function __construct(RenditionPermissionManager $renditionPermissionManager)
    {
        $this->renditionPermissionManager = $renditionPermissionManager;
    }

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof AssetRendition;
    }

    /**
     * @param AssetRendition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = null;
        $groupIds = [];
        if ($user instanceof RemoteUser) {
            $userId = $user->getId();
            $groupIds = $user->getGroupIds();
        }

        switch ($attribute) {
            case self::READ:
                return $this->renditionPermissionManager->isGranted(
                    $subject->getAsset(),
                    $subject->getDefinition()->getClass(),
                    $userId,
                    $groupIds
                );
        }

        return false;
    }
}
