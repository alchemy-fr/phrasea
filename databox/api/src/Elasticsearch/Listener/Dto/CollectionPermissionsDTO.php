<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener\Dto;

final readonly class CollectionPermissionsDTO
{
    public function __construct(
        public int $bestPrivacy,
        public string $absolutePath,
        public PermissionsDTO $permissions,
    ) {
    }
}
