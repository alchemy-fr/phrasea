<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceVoter extends AbstractVoter
{
    final public const SCOPE_PREFIX = 'ROLE_WORKSPACE:';
    private array $cache = [];

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace && !is_numeric($attribute);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return !is_numeric($attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Workspace::class, true);
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
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::CREATE => $this->isAdmin()
                || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::READ => $isOwner()
                || $subject->isPublic()
                || $this->security->isGranted(self::SCOPE_PREFIX.'READ')
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->isAdmin(),
            self::EDIT => $isOwner()
                || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT')
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || $this->isAdmin(),
            self::DELETE => $isOwner()
                || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE')
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || $this->isAdmin(),
            self::EDIT_PERMISSIONS => $isOwner()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->isAdmin(),
            default => false,
        };
    }
}
