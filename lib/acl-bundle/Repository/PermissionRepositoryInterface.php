<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;

interface PermissionRepositoryInterface
{
    /**
     * @return AccessControlEntryInterface[]
     */
    public function findAces(array $params = []): array;

    public function getAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array;

    /**
     * @return AccessControlEntryInterface[]
     */
    public function getObjectAces(string $objectType, ?string $objectId): array;

    public function updateOrCreateAce(string $userType, string $userId, string $objectType, ?string $objectId, int $permissions): ?AccessControlEntryInterface;

    public function deleteAce(string $userType, string $userId, string $objectType, ?string $objectId): void;
}
