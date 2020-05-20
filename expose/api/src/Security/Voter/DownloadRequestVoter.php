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
    const LIST = 'download_request:list';
    const READ = 'READ';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    private Security $security;
    private EntityManagerInterface $em;

    public function __construct(Security $security, EntityManagerInterface $em)
    {
        $this->security = $security;
        $this->em = $em;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof DownloadRequest
            || $attribute === self::LIST;
    }

    /**
     * @param Asset|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;
        $isAdmin = $isAuthenticated
            && ($this->security->isGranted('ROLE_PUBLISH')
                || $this->security->isGranted('ROLE_ADMIN')
            );

        switch ($attribute) {
            case self::LIST:
            case self::READ:
            case self::EDIT:
            case self::DELETE:
                return $isAdmin;
        }

        return false;
    }
}
