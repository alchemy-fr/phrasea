<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetDataTemplateVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof AssetDataTemplate;
    }

    /**
     * @param AssetDataTemplate $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $userId && $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::READ:
                return $subject->isPublic() || $isOwner || $this->security->isGranted(PermissionInterface::VIEW, $subject);
            case self::EDIT:
                return $isOwner || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::DELETE:
                return $isOwner || $this->security->isGranted(PermissionInterface::DELETE, $subject);
            case self::CREATE:
                return (bool) $userId;
        }

        return false;
    }
}
