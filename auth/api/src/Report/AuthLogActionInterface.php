<?php

declare(strict_types=1);

namespace App\Report;

interface AuthLogActionInterface
{
    public const CHANGE_PASSWORD = 'change_password';
    public const RESET_PASSWORD = 'reset_password';
    public const REQUEST_RESET_PASSWORD = 'request_reset_password';
    public const USER_AUTHENTICATION = 'user_authentication';
}
