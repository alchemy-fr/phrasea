<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\CollectionAsset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CollectionAssetVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof CollectionAsset;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, CollectionAsset::class, true);
    }

    /**
     * @param CollectionAsset $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;

        return match ($attribute) {
            self::CREATE => $this->security->isGranted(CollectionVoter::ASSET_CREATE, $subject->getCollection())
                && $this->security->isGranted(AbstractVoter::READ, $subject->getAsset()),
            self::DELETE => ($userId && $subject->getAsset()->getOwnerId() === $userId)
                || $this->security->isGranted(CollectionVoter::ASSET_DELETE, $subject->getCollection())
                || $this->hasAcl(PermissionInterface::OWNER, $subject->getAsset(), $token),
            default => false,
        };
    }
}
