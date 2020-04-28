<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

class RemoteAuthGroupRepository extends AbstractRemoteAuthRepository implements GroupRepositoryInterface
{
    public function getGroups(): array
    {
        return $this->serviceClient->getGroups($this->getAccessToken());
    }
}
