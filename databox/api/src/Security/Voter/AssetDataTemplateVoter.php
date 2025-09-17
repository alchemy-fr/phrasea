<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetDataTemplateVoter extends AbstractVoter
{
    final public const string SCOPE_PREFIX = 'asset-data-template:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetDataTemplate;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetDataTemplate::class, true);
    }

    /**
     * @param AssetDataTemplate $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, self::SCOPE_PREFIX, $attribute)) {
            return true;
        }

        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isOwner = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        return match ($attribute) {
            self::READ => $subject->isPublic() || $isOwner() || $this->hasAcl(PermissionInterface::VIEW, $subject, $token),
            self::EDIT => $isOwner() || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isOwner() || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::CREATE => (bool) $userId,
            default => false,
        };
    }
}
