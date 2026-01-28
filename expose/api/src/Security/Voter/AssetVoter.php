<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use Alchemy\AuthBundle\Security\Voter\JwtVoterTrait;
use App\Entity\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetVoter extends AbstractVoter
{
    use JwtVoterTrait;

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Asset::class, true);
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof Asset;
    }

    /**
     * @param Asset $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->isValidJWTForRequest()
                || $this->security->isGranted(PublicationVoter::READ_DETAILS, $subject->getPublication()),
            self::CREATE, self::DELETE, self::EDIT => $this->security->isGranted(PublicationVoter::EDIT, $subject->getPublication()),
            default => false,
        };
    }
}
