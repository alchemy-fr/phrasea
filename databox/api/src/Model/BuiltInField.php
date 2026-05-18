<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\BuiltInFieldProvider;

#[ApiResource(
    shortName: 'built-in-field',
    operations: [
        new Get(),
        new GetCollection(),
    ],
    provider: BuiltInFieldProvider::class,
)]
class BuiltInField
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $name,
        public string $key,
        public string $displayName,
        public string $type,
        public bool $multiple,
        public bool $facetEnabled,
        public bool $sortable,
        public bool $searchable,
    ) {
    }
}
