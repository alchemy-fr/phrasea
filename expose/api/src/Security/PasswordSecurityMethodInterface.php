<?php

declare(strict_types=1);

namespace App\Security;

interface PasswordSecurityMethodInterface
{
    public const string ERROR_NO_PASSWORD_PROVIDED = 'missing_password';
    public const string ERROR_INVALID_PASSWORD = 'invalid_password';
}
