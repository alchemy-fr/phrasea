<?php

declare(strict_types=1);

namespace App\Listener\OAuth;

abstract class Events
{
    const ON_ACCESS_TOKEN_DELIVERED = 'app.on_access_token_delivered';
}
