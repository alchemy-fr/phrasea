<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class PasswordBadge implements BadgeInterface
{
    public function __construct(private readonly string $passwords)
    {
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
