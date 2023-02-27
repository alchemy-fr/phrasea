<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

use Alchemy\AclBundle\Repository\AclUserRepositoryInterface;

interface UserRepositoryInterface extends AclUserRepositoryInterface
{
    public function getUsers(?int $limit = null, ?int $offset = null): array;
}
