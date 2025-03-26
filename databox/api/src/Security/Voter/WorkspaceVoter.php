<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkspaceVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'workspace:';
    }
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
                || $this->hasScope($token, $attribute),
            self::READ => $isOwner()
                || $subject->isPublic()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::VIEW, $subject, $token)
                || $this->isAdmin(),
            self::EDIT => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token)
                || $this->isAdmin(),
            self::DELETE => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token)
                || $this->isAdmin(),
            self::EDIT_PERMISSIONS, self::OWNER => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token)
                || $this->isAdmin(),
            self::OPERATOR => $isOwner()
                || $this->hasScope($token, $attribute)
                || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token),

            default => false,
        };
    }
}
