<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Symfony\Contracts\Cache\CacheInterface;

abstract class AbstractKeycloakRepository
{
    public function __construct(
        protected readonly ServiceAccountClient $serviceAccountClient,
        protected readonly KeycloakClient $oauthClient,
        protected readonly CacheInterface $keycloakRealmCache,
    ) {
    }

    protected function executeWithAccessToken(callable $callback): mixed
    {
        return $this->serviceAccountClient->executeWithAccessToken($callback);
    }
}
