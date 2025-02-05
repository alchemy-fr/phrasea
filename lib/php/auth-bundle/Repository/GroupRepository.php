<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

class GroupRepository extends AbstractKeycloakRepository implements GroupRepositoryInterface
{
    public function getGroups(array $options = []): array
    {
        if (isset($options['access_token'])) {
            return $this->oauthClient->getGroups($options['access_token'], $options);
        }

        return $this->keycloakRealmCache->get('groups', function () use ($options): array {
            return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getGroups($accessToken, $options));
        });
    }

    public function getGroup(string $groupId, array $options = []): ?array
    {
        if (isset($options['access_token'])) {
            return $this->oauthClient->getGroup($options['access_token'], $groupId, $options);
        }

        return $this->keycloakRealmCache->get('groups_'.$groupId, function () use ($groupId, $options): ?array {
            return $this->executeWithAccessToken(fn (string $accessToken): ?array => $this->oauthClient->getGroup($accessToken, $groupId, $options));
        });
    }
}
