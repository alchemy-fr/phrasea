<?php

declare(strict_types=1);

namespace App\Security\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class AssetTokenBadge implements BadgeInterface
{
    private string $accessToken;

    /**
     * AccessTokenBadge constructor.
     */
    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
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
