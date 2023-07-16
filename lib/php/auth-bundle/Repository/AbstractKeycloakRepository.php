<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Client\OAuthClient;

abstract class AbstractKeycloakRepository
{
    public function __construct(
        protected readonly ServiceAccountClient $serviceAccountClient,
        protected readonly OAuthClient $oauthClient,
    )
    {
    }

    protected function executeWithAccessToken(callable $callback): mixed
    {
        return $this->serviceAccountClient->executeWithAccessToken($callback);
    }
}
