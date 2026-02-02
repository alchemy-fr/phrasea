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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PublicationVoter extends AbstractVoter
{
    use JwtVoterTrait;

    final public const string PUBLISH = 'publication:publish';
    final public const string LIST_PUBLICATIONS = 'publication:index';
    final public const string READ_DETAILS = 'READ_DETAILS';

    public function __construct(
        private readonly PasswordTokenExtractor $passwordTokenExtractor,
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
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
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

                $password = $securityContainer->getSecurityOptions()['password'] ?? null;
                if (null === $password || $publicationPassword !== $password) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD);

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
}
