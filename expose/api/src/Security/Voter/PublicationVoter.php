<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use App\Entity\Publication;
use App\Security\Authentication\PasswordToken;
use App\Security\AuthenticationSecurityMethodInterface;
use App\Security\PasswordSecurityMethodInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class PublicationVoter extends Voter
{
    const PUBLISH = 'publication:publish';
    const CREATE = 'publication:create';
    const INDEX = 'publication:index';
    const READ = 'READ';
    const READ_DETAILS = 'READ_DETAILS';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

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
        return $subject instanceof Publication || self::CREATE === $attribute;
    }

    /**
     * @param Publication|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $isAdmin = $this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN');
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;

        switch ($attribute) {
            case self::CREATE:
                return $isAdmin || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::INDEX:
                return true;
            case self::READ:
                return $subject->isEnabled()
                    || $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId());
            case self::READ_DETAILS:
                return $isAdmin || ($subject->isEnabled() && $this->securityMethodPasses($subject, $token));
            case self::DELETE:
                return $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId())
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                    ;
            case self::EDIT:
                return $isAdmin
                    || ($isAuthenticated && $subject->getOwnerId() === $user->getId())
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject)
                    ;
            default:
                return false;
        }
    }

    protected function securityMethodPasses(Publication $publication, TokenInterface $token): bool
    {
        $securityContainer = $publication->getSecurityContainer();

        switch ($securityContainer->getSecurityMethod()) {
            case Publication::SECURITY_METHOD_NONE:
            return true;
            case Publication::SECURITY_METHOD_PASSWORD:
                if (!$token instanceof PasswordToken) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_NO_PASSWORD_PROVIDED);

                    return false;
                }

                $publicationPassword = $token->getPublicationPassword($securityContainer->getId());
                if (empty($publicationPassword)) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_NO_PASSWORD_PROVIDED);

                    return false;
                }

                if ($publicationPassword !== $securityContainer->getSecurityOptions()['password']) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD);

                    return false;
                }

                return true;
            case Publication::SECURITY_METHOD_AUTHENTICATION:
                if (!$token instanceof RemoteAuthToken) {
                    $publication->setAuthorizationError(AuthenticationSecurityMethodInterface::ERROR_NO_ACCESS_TOKEN);

                    return false;
                }

                return $this->security->isGranted(PermissionInterface::VIEW, $publication);
            default:
                return false;
        }
    }
}
