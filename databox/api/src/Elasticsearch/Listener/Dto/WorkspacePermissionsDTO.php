<?php

namespace App\Elasticsearch\Listener\Dto;

final readonly class WorkspacePermissionsDTO
{
    public function __construct(
        public array $users,
        public array $groups,
        public array $deleteUsers,
        public array $deleteGroups,
    ) {
    }
}
