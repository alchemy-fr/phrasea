<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Asset;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AssetVoter extends Voter
{
    use JwtVoterTrait;

    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string CREATE = 'CREATE';

    public function __construct(
        private readonly Security $security,
    ) {
    }

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
