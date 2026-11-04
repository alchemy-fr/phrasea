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

    /**
     * Not cached: the membership must be the current one.
     */
    public function getGroupMemberIds(string $groupId): array
    {
        $pageSize = 500;
        $ids = [];
        for ($first = 0;; $first += $pageSize) {
            $members = $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getGroupMembers($accessToken, $groupId, $first, $pageSize));
            foreach ($members as $member) {
                if (isset($member['id']) && false !== ($member['enabled'] ?? true)) {
                    $ids[] = (string) $member['id'];
                }
            }

            if (count($members) < $pageSize) {
                return array_values(array_unique($ids));
            }
        }
    }
}
