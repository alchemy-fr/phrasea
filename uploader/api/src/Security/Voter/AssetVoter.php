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
    final public const ACK = 'ACK';
    final public const DOWNLOAD = 'DOWNLOAD';
    final public const READ_METADATA = 'READ_META';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof Asset;
    }

    /**
     * @param AssetToken $token
     * @param Asset      $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
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
                case self::ACK:
                    if ($token->getAccessToken() === $subject->getToken()) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }
}
