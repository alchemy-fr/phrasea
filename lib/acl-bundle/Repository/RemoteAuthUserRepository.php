<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

class RemoteAuthUserRepository extends AbstractRemoteAuthRepository implements UserRepositoryInterface
{
    public function getUsers(): array
    {
        return $this->serviceClient->getUsers($this->getAccessToken());
    }
}
