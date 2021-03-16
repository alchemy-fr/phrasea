<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

class RemoteAuthGroupRepository extends AbstractRemoteAuthRepository implements GroupRepositoryInterface
{
    public function getGroups(?int $limit = null, ?int $offset = null): array
    {
        return $this->executeWithAccessToken(function (string $accessToken) use ($limit, $offset): array {
            return $this->serviceClient->getGroups($accessToken, $limit, $offset);
        });
    }
}
