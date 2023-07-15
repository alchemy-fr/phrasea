<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AuthBundle\Client\AdminClient;
use Alchemy\AuthBundle\Client\OAuthClient;

abstract class AbstractRemoteAuthRepository
{
    public function __construct(protected AdminClient $adminClient, protected OAuthClient $serviceClient)
    {
    }

    protected function executeWithAccessToken(callable $callback)
    {
        return $this->adminClient->executeWithAccessToken($callback);
    }
}
