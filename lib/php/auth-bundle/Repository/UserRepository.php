<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AuthBundle\Security\JwtUser;

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

    public function getUser(string $userId, array $options = []): ?array
    {
        if (isset($options['access_token'])) {
            return $this->oauthClient->getUser($options['access_token'], $userId, $options);
        }

        return $this->keycloakRealmCache->get('users_'.$userId, function () use ($userId): ?array {
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
