<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoterTrait;
use App\Entity\Publication;
use App\Security\AuthenticationSecurityMethodInterface;
use App\Security\PasswordSecurityMethodInterface;
use App\Security\PasswordTokenExtractor;
use App\Security\ScopeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PublicationVoter extends Voter
{
    use ScopeVoterTrait;
    use JwtVoterTrait;

    final public const PUBLISH = 'publication:publish';
    final public const CREATE = 'CREATE';
    final public const INDEX = 'publication:index';
    final public const READ = 'READ';
    final public const READ_DETAILS = 'READ_DETAILS';
    final public const OPERATOR = 'OPERATOR';
    final public const EDIT = 'EDIT';
    final public const DELETE = 'DELETE';

    public function __construct(
        private readonly Security $security,
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
        $isAdmin = $this->hasScope(ScopeInterface::SCOPE_PUBLISH, $token)
            || $this->security->isGranted(JwtUser::ROLE_ADMIN);
        $user = $token->getUser();
        $isAuthenticated = $user instanceof JwtUser;
        $isOwner = $isAuthenticated && $subject && $subject->getOwnerId() === $user->getId();

        $isPublicationVisible = $subject instanceof Publication && $subject->isVisible();

        return match ($attribute) {
            self::CREATE => $isAdmin
                || $this->security->isGranted(PermissionInterface::CREATE, new Publication()),
            self::INDEX => true,
            self::READ => $isPublicationVisible
                || $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            self::READ_DETAILS => $isAdmin
                || $this->isValidJWTForRequest()
                || ($isPublicationVisible && $this->securityMethodPasses($subject, $token))
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
            self::DELETE => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::DELETE, $subject),
            self::OPERATOR => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::OPERATOR, $subject),
            self::EDIT => $isAdmin
                || $isOwner
                || $this->security->isGranted(PermissionInterface::EDIT, $subject),
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

                if ($publicationPassword !== $securityContainer->getSecurityOptions()['password']) {
                    $publication->setAuthorizationError(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD);

                    return false;
                }

                return true;
            case Publication::SECURITY_METHOD_AUTHENTICATION:
                if (!$token instanceof JwtUser) {
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
