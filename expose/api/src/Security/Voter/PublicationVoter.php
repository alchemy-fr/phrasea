<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use App\Entity\Publication;
use App\Security\Authentication\JWTManager;
use App\Security\Authentication\PasswordToken;
use App\Security\AuthenticationSecurityMethodInterface;
use App\Security\PasswordSecurityMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class PublicationVoter extends Voter
{
    const PUBLISH = 'publication:publish';
    const CREATE = 'CREATE';
    const INDEX = 'publication:index';
    const READ = 'READ';
    const READ_DETAILS = 'READ_DETAILS';
    const OPERATOR = 'OPERATOR';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    private Security $security;
    private RequestStack $requestStack;
    private JWTManager $JWTManager;

    public function __construct(Security $security, RequestStack $requestStack, JWTManager $JWTManager)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->JWTManager = $JWTManager;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Publication;
    }

    private function isValidJWTForRequest(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (!$currentRequest instanceof Request) {
            return false;
        }

        $uri = $currentRequest->getUri();
        $token = $currentRequest->query->get('jwt');
        if (!$token) {
            return false;
        }

        try {
            $this->JWTManager->validateJWT($uri, $token);
        } catch (AccessDeniedHttpException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param Publication|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $isAdmin = $this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN');
        $user = $token->getUser();
        $isAuthenticated = $user instanceof RemoteUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        $isPublicationVisible = $subject instanceof Publication && $subject->isVisible();

        switch ($attribute) {
            case self::CREATE:
                return $isAdmin
                    || $this->security->isGranted(PermissionInterface::CREATE, new Publication());
            case self::INDEX:
                return true;
            case self::READ:
                return $isPublicationVisible
                    || $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::READ_DETAILS:
                return $isAdmin
                    || $this->isValidJWTForRequest()
                    || ($isPublicationVisible && $this->securityMethodPasses($subject, $token))
                    || $this->security->isGranted(PermissionInterface::EDIT, $subject);
            case self::DELETE:
                return $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::DELETE, $subject)
                    ;
            case self::OPERATOR:
                return $isAdmin
                    || $isOwner
                    || $this->security->isGranted(PermissionInterface::OPERATOR, $subject)
                    ;
            case self::EDIT:
                return $isAdmin
                    || $isOwner
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

                if (!$this->security->isGranted(PermissionInterface::VIEW, $publication)) {
                    $publication->setAuthorizationError(AuthenticationSecurityMethodInterface::ERROR_NOT_ALLOWED);

                    return false;
                } else {
                    return true;
                }
                // no break
            default:
                return false;
        }
    }
}
