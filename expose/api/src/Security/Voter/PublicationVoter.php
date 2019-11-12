<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use App\Entity\Asset;
use App\Entity\Publication;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class PublicationVoter extends Voter
{
    const PUBLISH = 'publication:publish';

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
        return $subject instanceof Publication || $attribute === self::PUBLISH;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($attribute === self::PUBLISH) {
            if ($token instanceof RemoteAuthToken && $token->hasScope('expose:publish')) {
                return true;
            } elseif ($this->security->isGranted('ROLE_ADMIN')) {
                return true;
            }
        }

        return false;
    }
}
