<?php

namespace App\Elasticsearch\Listener\Dto;

final readonly class CollectionPermissionsDTO
{
    public function __construct(
        public int $bestPrivacy,
        public string $absolutePath,
        public array $users,
        public array $groups,
        public array $deleteUsers,
        public array $deleteGroups,
    ) {
    }
}
