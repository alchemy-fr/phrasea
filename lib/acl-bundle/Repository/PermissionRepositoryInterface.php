<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;

interface PermissionRepositoryInterface
{
    public function getAce(string $userId, string $objectId): ?AccessControlEntryInterface;

    /**
     * @return AccessControlEntryInterface[]
     */
    public function getObjectAces(string $objectId): array;
}
