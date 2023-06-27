<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AssetVoter extends Voter
{
    final public const READ = 'READ';
    final public const EDIT = 'EDIT';
    final public const DELETE = 'DELETE';
    final public const CREATE = 'CREATE';

    public function __construct(private readonly Security $security)
    {
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
            self::READ => $this->security->isGranted(PublicationVoter::READ_DETAILS, $subject->getPublication()),
            self::CREATE, self::DELETE, self::EDIT => $this->security->isGranted(PublicationVoter::EDIT, $subject->getPublication()),
            default => false,
        };
    }
}
