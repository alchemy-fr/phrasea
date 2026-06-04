<?php

namespace App\Elasticsearch\Mapping;

use App\Attribute\Type\AttributeTypeInterface;

final readonly class FieldInfoDto
{
    public function __construct(
        public string $name,
        public AttributeTypeInterface $type,
        public bool $enabled,
    ) {
    }
}
