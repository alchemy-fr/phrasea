<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AuthBundle\Model\RemoteUser;

class UserRepository extends AbstractKeycloakRepository implements UserRepositoryInterface
{
    public function getUsers(int $limit = null, int $offset = null): array
    {
        return $this->executeWithAccessToken(fn (string $accessToken): array => $this->oauthClient->getUsers($accessToken, $limit, $offset));
    }

    public function getAclUsers(int $limit = null, int $offset = 0): array
    {
        return $this->getUsers($limit, $offset);
    }

    /**
     * @param RemoteUser $user
     */
    public function getAclGroupsId(AclUserInterface $user): array
    {
        return $user->getGroupIds();
    }
}
