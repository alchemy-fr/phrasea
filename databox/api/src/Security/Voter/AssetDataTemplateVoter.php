<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetDataTemplateVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetDataTemplate;
    }

    /**
     * @param AssetDataTemplate $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token) : bool
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::READ => $subject->isPublic() || $isOwner || $this->security->isGranted(PermissionInterface::VIEW, $subject),
            self::EDIT => $isOwner || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            self::DELETE => $isOwner || $this->security->isGranted(PermissionInterface::DELETE, $subject),
            self::CREATE => (bool) $userId,
            default => false,
        };
    }
}
