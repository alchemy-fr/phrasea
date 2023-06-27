<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

class RemoteAuthGroupRepository extends AbstractRemoteAuthRepository implements GroupRepositoryInterface
{
    public function getGroups(int $limit = null, int $offset = null): array
    {
        return $this->executeWithAccessToken(fn (string $accessToken): array => $this->serviceClient->getGroups($accessToken, $limit, $offset));
    }
}
