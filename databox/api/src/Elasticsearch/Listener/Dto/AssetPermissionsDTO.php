<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener\Dto;

final readonly class AssetPermissionsDTO
{
    public function __construct(
        public int $privacy,
        public PermissionsDTO $permissions,
        public array $collectionPaths,
        public array $stories,
    ) {
    }

    public function toDocument(): array
    {
        return [
            'privacy' => $this->privacy,
            'users' => $this->permissions->users,
            'groups' => $this->permissions->groups,
            'deleteGroups' => $this->permissions->deleteGroups,
            'deleteUsers' => $this->permissions->deleteUsers,
            'quarantineGroups' => $this->permissions->quarantineGroups,
            'quarantineUsers' => $this->permissions->quarantineUsers,
            'collectionPaths' => $this->collectionPaths,
            'stories' => $this->stories,
        ];
    }
}
