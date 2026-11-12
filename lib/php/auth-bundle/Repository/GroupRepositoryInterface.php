<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\GroupRepositoryInterface as AclGroupRepositoryInterface;

interface GroupRepositoryInterface extends AclGroupRepositoryInterface
{
    public function getGroups(array $options = []): array;

    /**
     * @return string[] IDs of the enabled direct members of the group
     */
    public function getGroupMemberIds(string $groupId): array;

    /**
     * Drops what is cached of the groups, and of the given one.
     */
    public function invalidateGroup(?string $groupId = null): void;
}
