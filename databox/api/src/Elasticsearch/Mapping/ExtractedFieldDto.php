<?php

namespace App\Elasticsearch\Mapping;

use App\Attribute\Type\AttributeTypeInterface;

final readonly class ExtractedFieldDto
{
    public function __construct(
        public string $name,
        public string $key,
        public AttributeTypeInterface $type,
        public bool $multiple,
    ) {
    }
}
