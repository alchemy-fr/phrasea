<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TextAttributeType extends AbstractAttributeType
{
    public const NAME = 'text';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function getSearchAnalyzer(string $language): ?string
    {
        return 'text_'.$language;
    }

    public function normalizeValue($value)
    {
        return (string) $value;
    }
}
