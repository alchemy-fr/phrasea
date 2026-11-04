<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetAttachment;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AssetAttachmentVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetAttachment;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetAttachment::class, true);
    }

    /**
     * @param AssetAttachment $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(self::READ, $subject->getAsset()),
            // Attaching exposes the attached asset (e.g. through a share of the host asset)
            self::CREATE => $this->security->isGranted(self::EDIT, $subject->getAsset())
                && $this->security->isGranted(self::READ, $subject->getAttachment()),
            self::EDIT, self::DELETE => $this->security->isGranted(self::EDIT, $subject->getAsset()),
            default => false,
        };
    }
}
