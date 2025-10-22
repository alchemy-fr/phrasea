<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetAttachment;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetAttachmentVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetAttachment;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetAttachment::class, true);
    }

    /**
     * @param AssetAttachment $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(AbstractVoter::READ, $subject->getAsset()),
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(AssetVoter::EDIT, $subject->getAsset()),
            default => false,
        };
    }
}
