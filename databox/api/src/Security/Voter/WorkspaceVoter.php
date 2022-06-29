<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceVoter extends AbstractVoter
{
    private array $cache = [];

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Workspace;
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $key = sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token));
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->doVote($attribute, $subject, $token);
    }

    private function doVote(string $attribute, Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : false;
        $isOwner = $subject->getOwnerId() === $userId;

        switch ($attribute) {
            case self::READ:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::VIEW, $subject);
            case self::EDIT:
                return false;
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::DELETE:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject);
            case self::EDIT_PERMISSIONS:
                return $isOwner
                    || $this->security->isGranted(PermissionInterface::OWNER, $subject);
        }

        return false;
    }
}
