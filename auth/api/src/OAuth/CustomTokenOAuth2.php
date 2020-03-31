<?php

declare(strict_types=1);

namespace App\OAuth;

use Alchemy\OAuthServerBundle\OAuth\ClientAllowedScopesOAuth2;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;

class CustomTokenOAuth2 extends ClientAllowedScopesOAuth2
{
    protected function genAccessToken()
    {
        return RemoteAuthToken::TOKEN_PREFIX.parent::genAccessToken();
    }
}
