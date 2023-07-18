<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoterTrait;
use App\Entity\Commit;
use App\Security\Authentication\AssetToken;
use App\Security\ScopeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CommitVoter extends Voter
{
    use ScopeVoterTrait;
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

        if ($this->security->isGranted(JwtUser::ROLE_ADMIN)) {
            return true;
        }

        switch ($attribute) {
            case self::READ:
                if ($this->hasScope(ScopeInterface::SCOPE_COMMIT_LIST, $token)) {
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
