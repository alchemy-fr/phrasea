<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use App\Security\ScopeInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class DownloadRequestVoter extends AbstractVoter
{
    final public const string LIST_DOWNLOAD_REQUESTS = 'download_request:list';

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof DownloadRequest
            || self::LIST_DOWNLOAD_REQUESTS === $attribute;
    }

    /**
     * @param Asset|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::LIST_DOWNLOAD_REQUESTS, self::READ, self::EDIT, self::DELETE => $this->hasScope(ScopeInterface::SCOPE_PUBLISH, '', false)
                    || $this->isAdmin(),
            default => false,
        };
    }
}
