<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AssetVoter extends Voter
{
    const READ = 'READ';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';
    const CREATE = 'CREATE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Asset;
    }

    /**
     * @param Asset $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::READ:
                return $this->security->isGranted(PublicationVoter::READ_DETAILS, $subject->getPublication());
            case self::CREATE:
            case self::DELETE:
            case self::EDIT:
                return $this->security->isGranted(PublicationVoter::EDIT, $subject->getPublication());
            default:
                return false;
        }
    }
}
