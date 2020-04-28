<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;

interface PermissionRepositoryInterface
{
    public function getAce(string $entityType, string $entityId, string $objectType, string $objectId): ?AccessControlEntryInterface;

    /**
     * @return AccessControlEntryInterface[]
     */
    public function getObjectAces(string $objectType, string $objectId): array;

    public function updateOrCreateAce(string $entityType, string $entityId, string $objectType, string $objectId, int $permissions): ?AccessControlEntryInterface;

    public function deleteAce(string $entityType, string $entityId, string $objectType, string $objectId): void;
}
