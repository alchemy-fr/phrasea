<?php

declare(strict_types=1);

namespace App\Security;

interface AuthenticationSecurityMethodInterface
{
    public const string ERROR_NO_ACCESS_TOKEN = 'missing_access_token';
    public const string ERROR_NOT_ALLOWED = 'not_allowed';
}
