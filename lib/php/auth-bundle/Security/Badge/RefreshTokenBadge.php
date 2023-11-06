<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

final class RefreshTokenBadge implements BadgeInterface
{
    public function __construct(
        private readonly string $refreshToken,
    )
    {
    }

    public function isResolved(): bool
    {
        return true;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }
}
