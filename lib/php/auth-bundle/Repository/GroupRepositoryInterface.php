<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\GroupRepositoryInterface as AclGroupRepositoryInterface;

interface GroupRepositoryInterface extends AclGroupRepositoryInterface
{
    public function getGroups(int $limit = null, int $offset = null): array;
}
