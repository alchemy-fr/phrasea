<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class AccessTokenBadge implements BadgeInterface
{
    public function __construct(
        private readonly string $accessToken,
    )
    {
    }

    public function isResolved(): bool
    {
        return true;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }
}
