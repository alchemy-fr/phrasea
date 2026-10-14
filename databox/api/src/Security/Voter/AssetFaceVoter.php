<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetFace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Faces (face recognition) are reserved to the users allowed to name them: the admins
 * and the holders of the "edit_faces" realm role, within the assets they can read.
 */
class AssetFaceVoter extends AbstractVoter
{
    final public const string ROLE_EDIT_FACES = 'ROLE_EDIT_FACES';

    /**
     * Subject-less attribute: may the user list and name faces at all.
     */
    final public const string EDIT_FACES = 'EDIT_FACES';

    protected function supports(string $attribute, $subject): bool
    {
        return self::EDIT_FACES === $attribute || $subject instanceof AssetFace;
    }

    #[\Override]
    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, [self::EDIT_FACES, self::READ, self::EDIT], true);
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return 'null' === $subjectType || is_a($subjectType, AssetFace::class, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->canEditFaces()) {
            return false;
        }

        return match ($attribute) {
            self::EDIT_FACES => true,
            self::READ, self::EDIT => $subject instanceof AssetFace
                && null !== $subject->getAsset()
                && $this->security->isGranted(self::READ, $subject->getAsset()),
            default => false,
        };
    }

    private function canEditFaces(): bool
    {
        return $this->isAuthenticated() && ($this->isAdmin() || $this->security->isGranted(self::ROLE_EDIT_FACES));
    }
}
