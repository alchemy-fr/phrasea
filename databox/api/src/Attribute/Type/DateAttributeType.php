<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class DateAttributeType implements AttributeTypeInterface
{
    public static function getName(): string
    {
        return 'date';
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    public function getSearchAnalyzer(string $language): ?string
    {
        return null;
    }
}
