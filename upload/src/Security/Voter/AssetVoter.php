<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Asset;
use App\Security\Authentication\AssetToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AssetVoter extends Voter
{
    const DOWNLOAD = 'download';
    const READ_METADATA = 'read_meta';

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
        return $subject instanceof Asset;
    }

    /**
     * @param AssetToken $token
     * @param Asset $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (null === $subject->getToken()) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        if ($token instanceof AssetToken) {
            switch ($attribute) {
                case self::DOWNLOAD:
                case self::READ_METADATA:
                    if ($token->getAccessToken() === $subject->getToken()) {
                        return true;
                    }
            }
        }

        return false;
    }
}
