<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AssetVoter extends Voter
{
    const READ = 'READ';
    const EDIT = 'EDIT';

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Asset;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::READ:
                // isGranted VIEW on asset
                // AND validate permissions on tags
                break;
            case self::EDIT:
                // isGranted EDIT on asset
                // BUT bypass permissions on tags (EDIT perms is sufficient)
                break;
        }
    }

}
