<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Commit;
use App\Security\Authentication\AssetToken;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CommitVoter extends Voter
{
    final public const ACK = 'ACK';
    final public const READ = 'READ';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof Commit;
    }

    /**
     * @param AssetToken $token
     * @param Commit     $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if (null === $subject->getToken()) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        switch ($attribute) {
            case self::READ:
                if ($this->security->isGranted('ROLE_COMMIT:LIST')) {
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
