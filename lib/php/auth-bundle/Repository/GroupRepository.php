<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

class GroupRepository extends AbstractKeycloakRepository implements GroupRepositoryInterface
{
    public function getGroups(?int $limit = null, ?int $offset = null, ?string $accessToken = null): array
    {
        if (null !== $accessToken) {
            return $this->oauthClient->getGroups($accessToken, $limit, $offset);
        }

        return $this->keycloakRealmCache->get('groups', function () use ($limit, $offset): array {
            return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getGroups($accessToken, $limit, $offset));
        });
    }

    public function getGroup(string $groupId, ?string $accessToken = null): ?array
    {
        if (null !== $accessToken) {
            return $this->oauthClient->getGroup($accessToken, $groupId);
        }

        return $this->keycloakRealmCache->get('groups_'.$groupId, function () use ($groupId): ?array {
            return $this->executeWithAccessToken(fn (string $accessToken): ?array => $this->oauthClient->getGroup($accessToken, $groupId));
        });
    }
}
