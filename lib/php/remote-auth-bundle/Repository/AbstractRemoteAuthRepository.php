<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;

abstract class AbstractRemoteAuthRepository
{
    protected AdminClient $adminClient;
    protected AuthServiceClient $serviceClient;

    public function __construct(AdminClient $adminClient, AuthServiceClient $serviceClient)
    {
        $this->adminClient = $adminClient;
        $this->serviceClient = $serviceClient;
    }

    protected function executeWithAccessToken(callable $callback)
    {
        return $this->adminClient->executeWithAccessToken($callback);
    }
}
