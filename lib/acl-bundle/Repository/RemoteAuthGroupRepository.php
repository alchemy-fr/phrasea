<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

class RemoteAuthGroupRepository extends AbstractRemoteAuthRepository implements GroupRepositoryInterface
{
    public function getGroups(?int $limit = null, ?int $offset = null): array
    {
        return $this->serviceClient->getGroups($this->getAccessToken(), $limit, $offset);
    }
}
