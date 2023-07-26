<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Api\Model\Input\WorkspaceInput;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceVoter extends AbstractVoter
{
    final public const SCOPE_PREFIX = 'ROLE_WORKSPACE:';
    private array $cache = [];

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace || $attribute === self::CREATE && $subject instanceof WorkspaceInput;
    }

    /**
     * @param Workspace|WorkspaceInput $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($subject instanceof WorkspaceInput) {
            return $this->doVote($attribute, $subject, $token);
        }

        $key = sprintf('%s:%s:%s', $attribute, $subject->getId(), spl_object_id($token));

        return $this->cache[$key] ?? ($this->cache[$key] = $this->doVote($attribute, $subject, $token));
    }

    private function doVote(string $attribute, Workspace|WorkspaceInput $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->security->isGranted(JwtUser::ROLE_ADMIN)
                || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::READ => $isOwner
                || $subject->isPublic()
                || $this->security->isGranted(self::SCOPE_PREFIX.'READ')
                || $this->security->isGranted(PermissionInterface::VIEW, $subject),
            self::EDIT => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            self::DELETE => $isOwner
                || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE')
                || $this->security->isGranted(PermissionInterface::DELETE, $subject),
            self::EDIT_PERMISSIONS => $isOwner
                || $this->security->isGranted(PermissionInterface::OWNER, $subject),
            default => false,
        };
    }
}
