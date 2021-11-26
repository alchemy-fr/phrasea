<?php

declare(strict_types=1);

namespace App\Attribute\Type;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function getSearchAnalyzer(string $language): ?string
    {
        return null;
    }

    public function normalizeValue($value)
    {
        return $value;
    }

    public function denormalizeValue($value)
    {
        return (string) $value;
    }
}
