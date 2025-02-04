<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\GroupRepositoryInterface as AclGroupRepositoryInterface;

interface GroupRepositoryInterface extends AclGroupRepositoryInterface
{
    public function getGroups(array $options = []): array;
}
