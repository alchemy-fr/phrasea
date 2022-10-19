<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Listener\OAuth;

class OAuthEvent
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }
}
