<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\BuiltInAttributeProvider;

#[ApiResource(
    shortName: 'built-in-attribute',
    operations: [
        new Get(),
        new GetCollection(),
    ],
    provider: BuiltInAttributeProvider::class,
)]
class BuiltInAttribute
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public string $displayName,
        public string $type,
        public bool $multiple,
        public bool $facetEnabled,
        public bool $sortable,
        public bool $searchable,
        public bool $enabled,
    ) {
    }
}
