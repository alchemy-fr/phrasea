<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

class RemoteAuthUserRepository extends AbstractRemoteAuthRepository implements UserRepositoryInterface
{
    public function getUsers(?int $limit = null, ?int $offset = null): array
    {
        return $this->executeWithAccessToken(function (string $accessToken) use ($limit, $offset): array {
            return $this->serviceClient->getUsers($accessToken, $limit, $offset);
        });
    }
}
