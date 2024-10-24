<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoterTrait;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use App\Security\ScopeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DownloadRequestVoter extends Voter
{
    use ScopeVoterTrait;

    final public const string LIST = 'download_request:list';
    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof DownloadRequest
            || self::LIST === $attribute;
    }

    /**
     * @param Asset|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::LIST, self::READ, self::EDIT, self::DELETE => $this->hasScope(ScopeInterface::SCOPE_PUBLISH, $token)
                    || $this->security->isGranted(JwtUser::ROLE_ADMIN),
            default => false,
        };
    }
}
