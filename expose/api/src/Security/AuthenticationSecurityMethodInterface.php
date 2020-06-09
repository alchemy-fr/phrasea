<?php

declare(strict_types=1);

namespace App\Security;

interface AuthenticationSecurityMethodInterface
{
    const ERROR_NO_ACCESS_TOKEN = 'missing_access_token';
    const ERROR_INVALID_CREDENTIALS = 'invalid_credentials';
    const ERROR_NOT_ALLOWED = 'not_allowed';
}
