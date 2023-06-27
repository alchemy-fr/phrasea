<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;

abstract class AbstractRemoteAuthRepository
{
    public function __construct(protected AdminClient $adminClient, protected AuthServiceClient $serviceClient)
    {
    }

    protected function executeWithAccessToken(callable $callback)
    {
        return $this->adminClient->executeWithAccessToken($callback);
    }
}
