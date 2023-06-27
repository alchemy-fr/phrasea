<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class PasswordBadge implements BadgeInterface
{
    private string $passwords;

    /**
     * AccessTokenBadge constructor.
     */
    public function __construct(string $passwords)
    {
        $this->passwords = $passwords;
    }

    public function isResolved(): bool
    {
        return true;
    }

    public function getPasswords(): string
    {
        return $this->passwords;
    }
}
