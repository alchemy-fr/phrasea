<?php

namespace App\Elasticsearch\Listener\Dto;

final readonly class AssetPermissionsDTO
{
    public function __construct(
        public int $privacy,
        public array $users,
        public array $groups,
        public array $deleteUsers,
        public array $deleteGroups,
        public array $collectionPaths,
        public array $stories,
    ) {
    }

    public function toDocument(): array
    {
        return [
            'privacy' => $this->privacy,
            'users' => $this->users,
            'groups' => $this->groups,
            'deleteGroups' => $this->deleteGroups,
            'deleteUsers' => $this->deleteUsers,
            'collectionPaths' => $this->collectionPaths,
            'stories' => $this->stories,
        ];
    }
}
