<?php

declare(strict_types=1);

namespace App\Report;

interface AuthLogActionInterface
{
    const CHANGE_PASSWORD = 'change_password';
    const RESET_PASSWORD = 'reset_password';
    const REQUEST_RESET_PASSWORD = 'request_reset_password';
    const USER_AUTHENTICATION = 'user_authentication';
}
