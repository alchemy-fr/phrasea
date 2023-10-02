<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

class GroupRepository extends AbstractKeycloakRepository implements GroupRepositoryInterface
{
    public function getGroups(int $limit = null, int $offset = null): array
    {
        return $this->keycloakRealmCache->get('groups', function () use ($limit, $offset): array {
            return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getGroups($accessToken, $limit, $offset));
        });
    }
}