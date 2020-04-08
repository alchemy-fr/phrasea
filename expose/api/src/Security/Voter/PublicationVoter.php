<?php

declare(strict_types=1);

namespace App\Security\Voter;

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
    const INDEX = 'publication:index';
    const READ = 'publication:read';
    const READ_DETAILS = 'publication:read_details';
    const EDIT = 'publication:edit';

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
        return $subject instanceof Publication || self::PUBLISH === $attribute;
    }

    /**
     * @param Publication|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $isAdmin = $this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN');

        switch ($attribute) {
            case self::PUBLISH:
                return $isAdmin;
            case self::INDEX:
            case self::READ:
                return $isAdmin || $subject->isEnabled();
            case self::READ_DETAILS:
                return $isAdmin || ($subject->isEnabled() && $this->securityMethodPasses($subject, $token));
            case self::EDIT:
                $user = $token->getUser();
                $isAuthenticated = $user instanceof RemoteUser;

                return $isAdmin ||
                    ($isAuthenticated && $subject->getOwnerId() === $user->getId());
            default:
                return false;
        }
    }

    protected function securityMethodPasses(Publication $publication, TokenInterface $token): bool
    {
        if (Publication::SECURITY_METHOD_NONE === $publication->getSecurityMethod()) {
            return true;
        }

        if (Publication::SECURITY_METHOD_PASSWORD === $publication->getSecurityMethod()) {
            if (!$token instanceof PasswordToken) {
                $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_NO_PASSWORD_PROVIDED);

                return false;
            }

            if ($token->getPassword() !== $publication->getSecurityOptions()['password']) {
                $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD);

                return false;
            }

            return true;
        }

        if (Publication::SECURITY_METHOD_AUTHENTICATION === $publication->getSecurityMethod()) {
            if (!$token instanceof RemoteAuthToken) {
                $publication->setAuthorizationError(AuthenticationSecurityMethodInterface::ERROR_NO_ACCESS_TOKEN);

                return false;
            }

            return true;
        }

        return false;
    }
}
