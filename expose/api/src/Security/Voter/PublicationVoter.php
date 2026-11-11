<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use Alchemy\AuthBundle\Security\Voter\JwtVoterTrait;
use App\Entity\Publication;
use App\Security\AuthenticationSecurityMethodInterface;
use App\Security\PasswordSecurityMethodInterface;
use App\Security\PasswordTokenExtractor;
use App\Security\ScopeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class PublicationVoter extends AbstractVoter
{
    use JwtVoterTrait;

    final public const string PUBLISH = 'publication:publish';
    final public const string LIST_PUBLICATIONS = 'publication:index';
    final public const string READ_DETAILS = 'READ_DETAILS';

    /**
     * Request attribute holding the outcome of each password checked by the request, by
     * security container: the voter is called several times per request, a wrong password
     * must count once.
     */
    private const string PASSWORD_CHECKS_ATTRIBUTE = '_publication_password_checks';

    public function __construct(
        private readonly PasswordTokenExtractor $passwordTokenExtractor,
        #[Autowire(service: 'limiter.publication_password')]
        private readonly RateLimiterFactoryInterface $passwordLimiter,
    ) {
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Publication::class, true);
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof Publication;
    }

    /**
     * @param Publication|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $isAdmin = $this->hasScope(ScopeInterface::SCOPE_PUBLISH, '', false)
            || $this->security->isGranted(JwtUser::ROLE_ADMIN);
        $user = $token->getUser();
        $isAuthenticated = $user instanceof JwtUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        $isPublicationVisible = $subject instanceof Publication && $subject->isVisible();

        return match ($attribute) {
            self::CREATE => $isAdmin
                || $this->hasAcl(PermissionInterface::CREATE, new Publication(), $token),
            self::LIST_PUBLICATIONS => true,
            self::READ => $isPublicationVisible
                || $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::READ_DETAILS => $isAdmin
                || $this->isValidJWTForRequest()
                || ($isPublicationVisible && $this->securityMethodPasses($subject, $token))
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            self::DELETE => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::DELETE, $subject, $token),
            self::OPERATOR => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token),
            self::EDIT => $isAdmin
                || $isOwner
                || $this->hasAcl(PermissionInterface::EDIT, $subject, $token),
            default => false,
        };
    }

    protected function securityMethodPasses(Publication $publication, TokenInterface $token): bool
    {
        $securityContainer = $publication->getSecurityContainer();

        switch ($securityContainer->getSecurityMethod()) {
            case Publication::SECURITY_METHOD_NONE:
                return true;
            case Publication::SECURITY_METHOD_PASSWORD:
                if (null === $publicationPassword = $this->passwordTokenExtractor->getPublicationPassword($securityContainer->getId())) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_NO_PASSWORD_PROVIDED);

                    return false;
                }

                $error = $this->checkPassword($securityContainer, $publicationPassword);
                if (null !== $error) {
                    $publication->setAuthorizationError($error);

                    return false;
                }

                return true;
            case Publication::SECURITY_METHOD_AUTHENTICATION:
                if (!$token instanceof JwtToken) {
                    $publication->setAuthorizationError(AuthenticationSecurityMethodInterface::ERROR_NO_ACCESS_TOKEN);

                    return false;
                }

                if (!$this->hasAcl(PermissionInterface::VIEW, $publication, $token)) {
                    $publication->setAuthorizationError(AuthenticationSecurityMethodInterface::ERROR_NOT_ALLOWED);

                    return false;
                }

                return true;

            default:
                return false;
        }
    }

    /**
     * @return string|null the authorization error, null when the password is valid
     */
    private function checkPassword(Publication $securityContainer, string $password): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        $checks = $request?->attributes->get(self::PASSWORD_CHECKS_ATTRIBUTE) ?? [];
        $containerId = $securityContainer->getId();
        if (array_key_exists($containerId, $checks)) {
            return $checks[$containerId];
        }

        $limiter = $this->passwordLimiter->create(($request?->getClientIp() ?? '').'|'.$containerId);
        if (0 === $limiter->consume(0)->getRemainingTokens()) {
            $error = PasswordSecurityMethodInterface::ERROR_TOO_MANY_ATTEMPTS;
        } elseif (true === $securityContainer->getSecurityConfig()?->isPasswordValid($password)) {
            $error = null;
        } else {
            $limiter->consume();
            $error = PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD;
        }

        $checks[$containerId] = $error;
        $request?->attributes->set(self::PASSWORD_CHECKS_ATTRIBUTE, $checks);

        return $error;
    }
}
