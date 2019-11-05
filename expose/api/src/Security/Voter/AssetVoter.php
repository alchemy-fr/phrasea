<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use App\Entity\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AssetVoter extends Voter
{
    const PUBLISH = 'asset:publish';

    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Asset || $attribute === self::PUBLISH;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($attribute === self::PUBLISH) {
            if ($token instanceof RemoteAuthToken && $token->hasScope('expose:publish')) {
                return true;
            }
            if ($this->security->isGranted('ROLE_ADMIN')) {
                return true;
            }
        }

        return false;
    }
}
