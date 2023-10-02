<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\AclUserRepositoryInterface;

interface UserRepositoryInterface extends AclUserRepositoryInterface
{
    public function getUsers(int $limit = null, int $offset = null): array;
    public function getUser(string $userId): ?array;
}
