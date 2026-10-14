<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\UserRepositoryInterface as AclUserRepositoryInterface;

interface UserRepositoryInterface extends AclUserRepositoryInterface
{
    public function getUsers(array $options = []): array;

    public function getUsersByIds(array $ids, array $options = []): array;

    public function getUser(string $userId, array $options = []): ?array;

    /**
     * @return string[] effective realm role names
     */
    public function getUserRoles(string $userId): array;

    /**
     * @return string[]
     */
    public function getUserGroupIds(string $userId): array;
}
