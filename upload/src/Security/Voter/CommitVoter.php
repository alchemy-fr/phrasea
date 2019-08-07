<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Asset;
use App\Entity\Commit;
use App\Security\Authentication\AssetToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class CommitVoter extends Voter
{
    const ACK = 'ack';

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
        return $subject instanceof Commit;
    }

    /**
     * @param AssetToken $token
     * @param Commit      $subject
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
                case self::ACK:
                    if ($token->getAccessToken() === $subject->getToken()) {
                        return true;
                    }
            }
        }

        return false;
    }
}
