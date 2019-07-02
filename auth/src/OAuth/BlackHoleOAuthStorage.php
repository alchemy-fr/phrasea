<?php

declare(strict_types=1);

namespace App\OAuth;

use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;

class BlackHoleOAuthStorage implements RequestDataStorageInterface
{
    public function fetch(ResourceOwnerInterface $resourceOwner, $key, $type = 'token')
    {
    }

    public function save(ResourceOwnerInterface $resourceOwner, $value, $type = 'token')
    {
    }
}
