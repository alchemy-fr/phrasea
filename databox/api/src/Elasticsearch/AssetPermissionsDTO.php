<?php

namespace App\Elasticsearch;

final readonly class AssetPermissionsDTO
{
    public function __construct(
        public int $privacy,
        public array $users,
        public array $groups,
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
            'collectionPaths' => $this->collectionPaths,
            'stories' => $this->stories,
        ];
    }
}
