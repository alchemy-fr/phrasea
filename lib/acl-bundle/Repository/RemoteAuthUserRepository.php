<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

class RemoteAuthUserRepository extends AbstractRemoteAuthRepository implements UserRepositoryInterface
{
    public function getUsers(?int $limit = null, ?int $offset = null): array
    {
        return $this->serviceClient->getUsers($this->getAccessToken(), $limit, $offset);
    }
}
