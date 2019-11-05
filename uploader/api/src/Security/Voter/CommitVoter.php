<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Commit;
use App\Security\Authentication\AssetToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class CommitVoter extends Voter
{
    const ACK = 'ack';
    const READ = 'read';

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
     * @param Commit $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (null === $subject->getToken()) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        switch ($attribute) {
            case self::READ:
                if ($this->security->isGranted('ROLE_UPLOADER:COMMIT_LIST')) {
                    return true;
                }
                if ($token instanceof AssetToken && $token->getAccessToken() === $subject->getToken()) {
                    return true;
                }
                break;
            case self::ACK:
                if ($token instanceof AssetToken && $token->getAccessToken() === $subject->getToken()) {
                    return true;
                }
                break;
        }

        return false;
    }
}
