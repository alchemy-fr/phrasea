<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AuthBundle\Security\JwtUser;

class UserRepository extends AbstractKeycloakRepository implements UserRepositoryInterface
{
    public function getUsers(int $limit = null, int $offset = null): array
    {
        return $this->keycloakRealmCache->get('users', function () use ($limit, $offset): array {
            return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getUsers($accessToken, $limit, $offset));
        });
    }

    public function getUser(string $userId): ?array
    {
        return $this->keycloakRealmCache->get('users_'.$userId, function () use ($userId): ?array {
            return $this->executeWithAccessToken(fn (string $accessToken): ?array => $this->oauthClient->getUser($accessToken, $userId));
        });
    }

    public function getAclUsers(int $limit = null, int $offset = 0): array
    {
        return $this->getUsers($limit, $offset);
    }

    /**
     * @param JwtUser $user
     */
    public function getAclGroupsId(AclUserInterface $user): array
    {
        return $user->getGroups();
    }
}
