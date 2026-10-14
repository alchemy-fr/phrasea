<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\AssetExport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AssetExportVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetExport;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetExport::class, true);
    }

    /**
     * @param AssetExport $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return match ($attribute) {
            // An export exposes a signed download URL: only its owner reads it
            self::READ => $user instanceof JwtUser && $subject->getOwnerId() === $user->getId(),
            default => false,
        };
    }
}
