<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Psr\Cache\CacheItemPoolInterface;

class UserRepository extends AbstractKeycloakRepository implements UserRepositoryInterface
{
    public function getUsers(array $options = []): array
    {
        $accessToken = $options['access_token'] ?? null;
        unset($options['access_token']);
        if (null !== $accessToken) {
            return $this->oauthClient->getUsers($accessToken, $options);
        }

        return $this->keycloakRealmCache->get('users', function () use ($options): array {
            return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getUsers($accessToken, $options));
        });
    }

    public function getUsersByIds(array $ids, array $options = []): array
    {
        $accessToken = $options['access_token'] ?? null;
        unset($options['access_token']);
        if (null !== $accessToken) {
            return $this->oauthClient->getUsersByIds($accessToken, $ids, $options);
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        $users = $this->keycloakRealmCache->get('ubid_'.count($ids).'_'.md5(implode(',', $ids)), function () use (
            $ids,
            $options
        ): array {
            return $this->executeWithAccessToken(fn (string $accessToken,
            ): array => $this->oauthClient->getUsersByIds($accessToken, $ids, $options));
        });

        foreach ($users as $userId => $user) {
            if ($this->keycloakRealmCache instanceof CacheItemPoolInterface) {
                $item = $this->keycloakRealmCache->getItem('u_'.$userId);
                $item->set($user);
                $this->keycloakRealmCache->save($item);
            }
        }

        return $users;
    }

    public function getUser(string $userId, array $options = []): ?array
    {
        if (isset($options['access_token'])) {
            return $this->oauthClient->getUser($options['access_token'], $userId, $options);
        }

        return $this->keycloakRealmCache->get('u_'.$userId, function () use ($userId): ?array {
            return $this->executeWithAccessToken(fn (string $accessToken): ?array => $this->oauthClient->getUser($accessToken, $userId));
        });
    }

    /**
     * @param JwtUser $user
     */
    public function getAclGroupsId(AclUserInterface $user): array
    {
        return $user->getGroups();
    }
}
