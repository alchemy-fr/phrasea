<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class DownloadRequestVoter extends Voter
{
    final public const LIST = 'download_request:list';
    final public const READ = 'READ';
    final public const EDIT = 'EDIT';
    final public const DELETE = 'DELETE';

    public function __construct(private readonly Security $security, private readonly EntityManagerInterface $em)
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
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;
        $isAdmin = $isAuthenticated
            && ($this->security->isGranted('ROLE_PUBLISH')
                || $this->security->isGranted('ROLE_ADMIN')
            );
        return match ($attribute) {
            self::LIST, self::READ, self::EDIT, self::DELETE => $isAdmin,
            default => false,
        };
    }
}
