<?php

declare(strict_types=1);

namespace App\Util;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\Attribute\Required;

trait SecurityAwareTrait
{
    protected Security $security;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function isSuperAdmin(): bool
    {
        return $this->security->isGranted(SuperAdminVoter::ROLE);
    }

    protected function isAdmin(): bool
    {
        return $this->security->isGranted(JwtUser::ROLE_ADMIN);
    }

    protected function isGranted(mixed $attributes, mixed $subject = null): bool
    {
        return $this->security->isGranted($attributes, $subject);
    }

    protected function getTokenId(): string
    {
        if (is_object($this->security->getToken())) {
            return (string) spl_object_id($this->security->getToken());
        }

        return 'no_token';
    }

    protected function getUserCacheId(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? '__no_user__';
    }

    protected function getStrictUser(): JwtUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        return $user;
    }

    protected function getUser(): ?JwtUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof JwtUser) {
            return null;
        }

        return $user;
    }

    public function denyAccessUnlessGranted(mixed $attributes, mixed $subject = null, ?string $message = null): void
    {
        if (!$this->isGranted($attributes, $subject)) {
            throw new AccessDeniedException($message ?? 'Access denied.');
        }
    }
}
