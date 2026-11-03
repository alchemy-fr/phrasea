<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\AssetImport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * An import is managed by its owner, and by whoever can create assets in its workspace
 * (imports received by webhook included).
 */
class AssetImportVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetImport;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetImport::class, true);
    }

    /**
     * @param AssetImport $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::READ, self::DELETE => $this->isOwner($subject, $token)
                || (null !== $subject->getWorkspace() && $this->security->isGranted(AssetContainerVoterInterface::ASSET_CREATE, $subject->getWorkspace())),
            default => false,
        };
    }

    private function isOwner(AssetImport $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof JwtUser && $subject->getOwnerId() === $user->getId();
    }
}
