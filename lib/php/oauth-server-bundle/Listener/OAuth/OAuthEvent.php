<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Listener\OAuth;

class OAuthEvent
{
    public function __construct(private $user)
    {
    }

    public function getUser()
    {
        return $this->user;
    }
}
