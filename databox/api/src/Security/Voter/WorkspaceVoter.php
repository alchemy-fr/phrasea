<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceVoter extends AbstractVoter
{
    private array $cache = [];

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace;
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $key = sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token));

        return $this->cache[$key] ?? ($this->cache[$key] = $this->doVote($attribute, $subject, $token));
    }

    private function doVote(string $attribute, Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::READ => $isOwner
                || $subject->isPublic()
                || $this->security->isGranted(PermissionInterface::VIEW, $subject),
            self::EDIT => $isOwner
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            self::DELETE => $isOwner
                || $this->security->isGranted(PermissionInterface::DELETE, $subject),
            self::EDIT_PERMISSIONS => $isOwner
                || $this->security->isGranted(PermissionInterface::OWNER, $subject),
            default => false,
        };
    }
}
