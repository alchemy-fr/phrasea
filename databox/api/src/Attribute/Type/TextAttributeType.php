<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\Mapping\IndexMappingUpdater;

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

    public function getElasticSearchMapping(string $language): array
    {
        $mapping = [];
        if (IndexMappingUpdater::NO_LOCALE !== $language) {
            $mapping['analyzer'] = 'text_'.$language;
        }

        return $mapping;
    }

    public function normalizeValue($value)
    {
        return (string)$value;
    }

    public function isLocaleAware(): bool
    {
        return true;
    }
}
