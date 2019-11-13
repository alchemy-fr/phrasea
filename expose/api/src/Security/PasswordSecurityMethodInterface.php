<?php

declare(strict_types=1);

namespace App\Security;

interface PasswordSecurityMethodInterface
{
    const ERROR_NO_PASSWORD_PROVIDED = 'missing_password';
    const ERROR_INVALID_PASSWORD = 'invalid_password';
}
